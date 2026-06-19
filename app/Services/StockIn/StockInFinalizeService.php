<?php

namespace App\Services\StockIn;

use App\Exceptions\BusinessLogicException;
use App\Enums\AuditAction;
use App\Models\InstrumentSet;
use App\Models\Lot;
use App\Models\LotHolding;
use App\Models\LotMovement;
use App\Models\Product;
use App\Models\SetInstrument;
use App\Models\SetInstrumentInstance;
use App\Models\StockIn;
use App\Models\StockInItem;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\QrLabel\PrintJobService;
use App\Services\QrLabel\QrPayloadService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class StockInFinalizeService
{
    public function __construct(
        private readonly QrPayloadService $qrPayloadService,
        private readonly PrintJobService $printJobService,
        private readonly AuditLogService $auditLogService
    ) {
    }

    /**
     * @return array{stock_in: StockIn, lots: Collection<int, Lot>}
     */
    public function finalize(StockIn $stockIn, User $actor): array
    {
        return DB::transaction(function () use ($stockIn, $actor) {
            $session = StockIn::query()
                ->lockForUpdate()
                ->with(['stockInItems'])
                ->findOrFail($stockIn->id);

            if ($session->status !== 'draft') {
                throw new BusinessLogicException('Only draft stock-in sessions can be finalized.');
            }

            if ($session->stockInItems->isEmpty()) {
                throw new BusinessLogicException('Cannot finalize an empty stock-in session.');
            }

            $createdLots = new Collection();

            foreach ($session->stockInItems as $item) {
                $createdLots->push($this->createLotForItem($session, $item, $actor));
            }

            // Generate QR labels and initial print jobs for every created lot
            foreach ($createdLots as $lot) {
                $this->printJobService->createPrintJob(lot: $lot, actor: $actor);
            }

            $createdLots->load('setInstrumentInstances.setInstrument');

            $session->fill([
                'status' => 'finalized',
                'confirmed_at' => now(),
                'confirmed_by_user_id' => $actor->id,
            ])->save();

            // Audit the finalization event
            $this->auditLogService->logModelAction(
                auditableType: StockIn::class,
                auditableId:   $session->id,
                actionType:    AuditAction::STOCK_IN_FINALIZED,
                actor:         $actor,
                description:   sprintf(
                    'Stock-in session %s finalized — %d lot(s) created.',
                    $session->session_no,
                    $createdLots->count()
                ),
                after: [
                    'status'          => 'finalized',
                    'total_lots'      => $createdLots->count(),
                    'confirmed_at'    => now()->toIso8601String(),
                ],
            );

            return [
                'stock_in' => $session->refresh()->load([
                    'supplier:id,supplier_name',
                    'picUser:id,full_name',
                    'confirmedByUser:id,full_name',
                'stockInItems.product:id,ref_num,product_name',
                'stockInItems.instrumentSet:id,set_code,set_name',
                'stockInItems.lot:id,lot_number,status',
                'stockInItems.lot.setInstrumentInstances.setInstrument:id,code,name',
            ]),
                'lots' => $createdLots,
            ];
        });
    }

    private function createLotForItem(StockIn $stockIn, StockInItem $item, User $actor): Lot
    {
        // Set-instance entry: mint a Lot tagged to the InstrumentSet, not a Product.
        if ($item->isSetEntry()) {
            return $this->createSetInstanceLot($stockIn, $item, $actor);
        }

        $requiresLotTracking = $this->requiresLotTracking((int) $item->product_id);
        $incomingLotNumber = trim((string) ($item->scanned_lot_number ?? ''));
        $isHolding = $requiresLotTracking && ((bool) $item->missing_lot_flag || $incomingLotNumber === '');

        if ($isHolding) {
            $lotNumber = $this->generateHoldingLotNumber($stockIn, $item);
        } elseif ($incomingLotNumber !== '') {
            $lotNumber = $incomingLotNumber;
        } else {
            $lotNumber = $this->generateAutoLotNumber($stockIn, $item);
        }

        if (Lot::query()->where('lot_number', $lotNumber)->exists()) {
            throw new BusinessLogicException("Lot number {$lotNumber} already exists in inventory.");
        }

        $lot = Lot::query()->create([
            'product_id' => $item->product_id,
            'supplier_id' => $stockIn->supplier_id,
            'lot_number' => $lotNumber,

            'is_system_generated_lot' => $isHolding,
            'supplier_batch_code' => $item->supplier_batch_code,
            'expiry_date' => $item->expiry_date,
            'status' => $isHolding ? 'holding' : 'available',
            'current_location_type' => 'warehouse',
            'current_location_id' => null,
            'remarks' => $item->remarks,
            'received_at' => $stockIn->stock_in_at,
        ]);

        $item->fill(['lot_id' => $lot->id])->save();

        LotMovement::query()->create([
            'lot_id' => $lot->id,
            'movement_type' => 'stock_in',
            'reference_type' => StockIn::class,
            'reference_id' => $stockIn->id,
            'from_status' => null,
            'to_status' => $lot->status,
            'from_location_type' => null,
            'from_location_id' => null,
            'to_location_type' => $lot->current_location_type,
            'to_location_id' => $lot->current_location_id,
            'performed_at' => now(),
            'performed_by_user_id' => $actor->id,
            'remarks' => 'Lot created during stock-in finalization',
        ]);

        if ($isHolding) {
            LotHolding::query()->create([
                'lot_id' => $lot->id,
                'holding_reason' => 'Missing lot number during stock-in capture',
                'assigned_at' => now(),
                'assigned_by_user_id' => $actor->id,
                'remarks' => $item->entry_override_reason,
            ]);
        }

        return $lot;
    }

    private function createSetInstrumentInstances(Lot $lot, StockIn $stockIn, StockInItem $item): void
    {
        if (!$lot->instrument_set_id) {
            throw new BusinessLogicException('Selected instrument set is not found.');
        }

        $setInstruments = SetInstrument::query()
            ->where('instrument_set_id', $lot->instrument_set_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'instrument_set_id', 'quantity']);

        foreach ($setInstruments as $setInstrument) {
            for ($sequence = 1; $sequence <= max(1, (int) $setInstrument->quantity); $sequence++) {
                SetInstrumentInstance::query()->create([
                    'lot_id' => $lot->id,
                    'instrument_set_id' => $lot->instrument_set_id,
                    'set_instrument_id' => $setInstrument->id,
                    'stock_in_id' => $stockIn->id,
                    'stock_in_item_id' => $item->id,
                    'instance_number' => $this->generateSetInstrumentInstanceNumber($stockIn, $lot, $setInstrument, $sequence),
                    'status' => 'available',
                    'remarks' => $item->remarks,
                ]);
            }
        }
    }

    /**
     * Mints a Lot that represents a single physical instrument-set instance.
     * The lot_number is derived from the set code so it is human-readable on
     * the QR label and easy to reconcile against.
     */
    private function createSetInstanceLot(StockIn $stockIn, StockInItem $item, User $actor): Lot
    {
        $set = InstrumentSet::query()->find($item->instrument_set_id, ['id', 'set_code', 'set_name']);

        if (!$set) {
            throw new BusinessLogicException('Selected instrument set is not found.');
        }

        $lotNumber = $this->generateSetInstanceLotNumber($set);

        $lot = Lot::query()->create([
            'product_id' => null,
            'instrument_set_id' => $set->id,
            'supplier_id' => $stockIn->supplier_id,
            'lot_number' => $lotNumber,

            'is_system_generated_lot' => true,
            'supplier_batch_code' => '',
            'expiry_date' => null,
            'status' => 'available',
            'current_location_type' => 'warehouse',
            'current_location_id' => null,
            'remarks' => $item->remarks,
            'received_at' => $stockIn->stock_in_at,
        ]);

        $item->fill(['lot_id' => $lot->id])->save();
        $this->createSetInstrumentInstances($lot, $stockIn, $item);

        LotMovement::query()->create([
            'lot_id' => $lot->id,
            'movement_type' => 'stock_in',
            'reference_type' => StockIn::class,
            'reference_id' => $stockIn->id,
            'from_status' => null,
            'to_status' => $lot->status,
            'from_location_type' => null,
            'from_location_id' => null,
            'to_location_type' => $lot->current_location_type,
            'to_location_id' => $lot->current_location_id,
            'performed_at' => now(),
            'performed_by_user_id' => $actor->id,
            'remarks' => 'Set instance created during stock-in finalization',
        ]);

        return $lot;
    }

    private function generateSetInstanceLotNumber(InstrumentSet $set): string
    {
        $codePart = $set->set_code !== null && $set->set_code !== ''
            ? preg_replace('/[^A-Z0-9_-]+/', '', strtoupper((string) $set->set_code))
            : 'SET' . $set->id;

        $datePart = now()->format('Ymd');
        $base = sprintf('SET-%s-%s', $codePart, $datePart);

        // Append a 4-digit sequence within the day so each instance is unique.
        for ($attempt = 1; $attempt <= 9999; $attempt++) {
            $candidate = sprintf('%s-%04d', $base, $attempt);

            if (!Lot::query()->where('lot_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new BusinessLogicException('Unable to generate unique set-instance lot number.');
    }

    private function generateSetInstrumentInstanceNumber(
        StockIn $stockIn,
        Lot $lot,
        SetInstrument $setInstrument,
        int $sequence
    ): string {
        $datePart = $stockIn->stock_in_at?->format('Ymd') ?? now()->format('Ymd');
        $base = sprintf(
            'INS-%s-%d-%d-%03d',
            $datePart,
            $lot->id,
            $setInstrument->id,
            $sequence
        );

        if (!SetInstrumentInstance::query()->where('instance_number', $base)->exists()) {
            return $base;
        }

        for ($attempt = 1; $attempt <= 99; $attempt++) {
            $candidate = sprintf('%s-%02d', $base, $attempt);

            if (!SetInstrumentInstance::query()->where('instance_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new BusinessLogicException('Unable to generate unique set instrument instance number.');
    }

    private function generateHoldingLotNumber(StockIn $stockIn, StockInItem $item): string
    {
        $base = sprintf('HOLD-%s-%d-%d', now()->format('YmdHis'), $stockIn->id, $item->id);

        if (!Lot::query()->where('lot_number', $base)->exists()) {
            return $base;
        }

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = $base . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            if (!Lot::query()->where('lot_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new BusinessLogicException('Unable to generate unique holding lot number.');
    }

    private function generateAutoLotNumber(StockIn $stockIn, StockInItem $item): string
    {
        $base = sprintf('AUTO-%s-%d-%d', now()->format('YmdHis'), $stockIn->id, $item->id);

        if (!Lot::query()->where('lot_number', $base)->exists()) {
            return $base;
        }

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = $base . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            if (!Lot::query()->where('lot_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new BusinessLogicException('Unable to generate unique lot number.');
    }

    private function requiresLotTracking(int $productId): bool
    {
        $product = Product::query()->select(['id', 'requires_lot'])->find($productId);

        if (!$product) {
            throw new BusinessLogicException('Selected product is not found.');
        }

        return (bool) $product->requires_lot;
    }
}
