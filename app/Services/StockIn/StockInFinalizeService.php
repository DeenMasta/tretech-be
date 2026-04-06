<?php

namespace App\Services\StockIn;

use App\Exceptions\BusinessLogicException;
use App\Enums\AuditAction;
use App\Models\Lot;
use App\Models\LotHolding;
use App\Models\LotMovement;
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
                    'stockInItems.lot:id,lot_number,status',
                ]),
                'lots' => $createdLots,
            ];
        });
    }

    private function createLotForItem(StockIn $stockIn, StockInItem $item, User $actor): Lot
    {
        $incomingLotNumber = trim((string) ($item->scanned_lot_number ?? ''));
        $isHolding = (bool) $item->missing_lot_flag || $incomingLotNumber === '';

        $lotNumber = $isHolding
            ? $this->generateHoldingLotNumber($stockIn, $item)
            : $incomingLotNumber;

        if (Lot::query()->where('lot_number', $lotNumber)->exists()) {
            throw new BusinessLogicException("Lot number {$lotNumber} already exists in inventory.");
        }

        $lot = Lot::query()->create([
            'product_id' => $item->product_id,
            'supplier_id' => $stockIn->supplier_id,
            'lot_number' => $lotNumber,
            'original_lot_number' => $isHolding ? ($incomingLotNumber !== '' ? $incomingLotNumber : null) : null,
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
}
