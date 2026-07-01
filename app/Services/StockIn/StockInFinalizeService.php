<?php

namespace App\Services\StockIn;

use App\Exceptions\BusinessLogicException;
use App\Enums\AuditAction;
use App\Models\InstrumentSet;
use App\Models\InstrumentSetItem;
use App\Models\Lot;
use App\Models\LotHolding;
use App\Models\LotMovement;
use App\Models\Product;
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
                if ($item->isSetEntry()) {
                    // Unpack the set: creates one product lot per blueprint component.
                    $componentLots = $this->unpackSetToComponentLots($session, $item, $actor);
                    $createdLots = $createdLots->concat($componentLots);
                } else {
                    $createdLots->push($this->createProductLot($session, $item, $actor));
                }
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
                'stockInItems.instrumentSet.instrumentSetItems.product:id,ref_num,product_name',
                'stockInItems.lot:id,lot_number,status',
            ]),
                'lots' => $createdLots,
            ];
        });
    }

    private function createProductLot(StockIn $stockIn, StockInItem $item, User $actor): Lot
    {

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

        $qty = $item->quantity ?? 1;

        $lot = Lot::query()
            ->where('lot_number', $lotNumber)
            ->where('product_id', $item->product_id)
            ->first();

        if ($lot) {
            $lot->quantity += $qty;
            $lot->quantity_available += $qty;
            $lot->save();
        } else {
            $lot = Lot::query()->create([
                'product_id' => $item->product_id,
                'supplier_id' => $stockIn->supplier_id,
                'lot_number' => $lotNumber,

                'is_system_generated_lot' => $isHolding,
                'manufacturing_date' => $item->manufacturing_date,
                'expiry_date' => $item->expiry_date,
                'status' => $isHolding ? 'holding' : 'available',
                'current_location_type' => 'warehouse',
                'current_location_id' => null,
                'remarks' => $item->remarks,
                'received_at' => $stockIn->stock_in_at,
                'quantity' => $qty,
                'quantity_available' => $qty,
                'quantity_consigned' => 0,
            ]);
        }

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
            'remarks' => 'Lot updated during stock-in finalization',
            'quantity' => $qty,
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

    /**
     * Unpacks an InstrumentSet stock-in item into individual product lots,
     * one per blueprint component (instrumentSetItems). The Set itself no longer
     * gets its own Lot — instead each component product receives stock.
     *
     * For example: receiving 2× Set[4×ProductA, 3×ProductB] →
     *   8 units added to ProductA's lot, 6 units added to ProductB's lot.
     *
     * @return Collection<int, Lot>
     */
    private function unpackSetToComponentLots(StockIn $stockIn, StockInItem $item, User $actor): Collection
    {
        $set = InstrumentSet::query()
            ->with(['instrumentSetItems.product:id,ref_num,product_name,requires_lot'])
            ->find($item->instrument_set_id, ['id', 'set_code', 'set_name']);

        if (!$set) {
            throw new BusinessLogicException('Selected instrument set is not found.');
        }

        if ($set->instrumentSetItems->isEmpty()) {
            throw new BusinessLogicException(
                "Instrument set '{$set->set_name}' has no component products defined. Please add products to the set before stocking in."
            );
        }

        $setQty = $item->quantity ?? 1;
        $createdLots = new Collection();

        foreach ($set->instrumentSetItems as $setItem) {
            $componentQty = $setItem->quantity * $setQty;
            $productId    = $setItem->product_id;

            // Auto-generate a lot number for the component product (no lot/expiry on set components)
            $lotNumber = $this->generateAutoComponentLotNumber($stockIn, $set, $setItem);

            // Find existing lot for same product from same set receipt, or create new
            $lot = Lot::query()
                ->where('lot_number', $lotNumber)
                ->where('product_id', $productId)
                ->first();

            if ($lot) {
                $lot->quantity           += $componentQty;
                $lot->quantity_available += $componentQty;
                
                if ($lot->status === 'depleted') {
                    $lot->status = 'available';
                }
                
                $lot->current_location_type = 'warehouse';
                $lot->current_location_id   = null;
                
                $lot->save();
            } else {
                $lot = Lot::query()->create([
                    'product_id'              => $productId,
                    'instrument_set_id'       => $set->id,  // tagged to know it came from a set receipt
                    'supplier_id'             => $stockIn->supplier_id,
                    'lot_number'              => $lotNumber,
                    'is_system_generated_lot' => true,
                    'manufacturing_date'     => null,
                    'expiry_date'             => null,
                    'status'                  => 'available',
                    'current_location_type'   => 'warehouse',
                    'current_location_id'     => null,
                    'remarks'                 => $item->remarks,
                    'received_at'             => $stockIn->stock_in_at,
                    'quantity'                => $componentQty,
                    'quantity_available'      => $componentQty,
                    'quantity_consigned'      => 0,
                ]);
            }

            // Link the first component lot back to the stock-in item (for reference)
            if ($createdLots->isEmpty()) {
                $item->fill(['lot_id' => $lot->id])->save();
            }

            LotMovement::query()->create([
                'lot_id'               => $lot->id,
                'movement_type'        => 'stock_in',
                'reference_type'       => StockIn::class,
                'reference_id'         => $stockIn->id,
                'from_status'          => null,
                'to_status'            => $lot->status,
                'from_location_type'   => null,
                'from_location_id'     => null,
                'to_location_type'     => $lot->current_location_type,
                'to_location_id'       => $lot->current_location_id,
                'performed_at'         => now(),
                'performed_by_user_id' => $actor->id,
                'remarks'              => "Unpacked from set '{$set->set_name}' during stock-in finalization",
                'quantity'             => $componentQty,
            ]);

            $createdLots->push($lot);
        }

        return $createdLots;
    }

    /**
     * Generate a stable, human-readable lot number for a component product
     * that came from unpacking a specific set during a stock-in session.
     * Format: SETCOMP-{SET_CODE}-{PRODUCT_ID}-{DATE}
     */
    private function generateAutoComponentLotNumber(StockIn $stockIn, InstrumentSet $set, InstrumentSetItem $setItem): string
    {
        $codePart  = $set->set_code !== null && $set->set_code !== ''
            ? preg_replace('/[^A-Z0-9_-]+/', '', strtoupper((string) $set->set_code))
            : 'SET' . $set->id;
        $datePart  = now()->format('Ymd');
        $base      = sprintf('SETCOMP-%s-P%d-%s', $codePart, $setItem->product_id, $datePart);

        // The base itself is unique enough for one set-product per day;
        // append sequence only if there is a collision.
        if (!Lot::query()->where('lot_number', $base)->where('product_id', '!=', $setItem->product_id)->exists()) {
            return $base;
        }

        for ($attempt = 1; $attempt <= 9999; $attempt++) {
            $candidate = sprintf('%s-%04d', $base, $attempt);
            if (!Lot::query()->where('lot_number', $candidate)->where('product_id', '!=', $setItem->product_id)->exists()) {
                return $candidate;
            }
        }

        throw new BusinessLogicException('Unable to generate unique component lot number.');
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
