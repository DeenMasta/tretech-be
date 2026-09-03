<?php

namespace App\Services\StockIn;

use App\Enums\AuditAction;
use App\Exceptions\BusinessLogicException;
use App\Models\InstrumentSet;
use App\Models\Lot;
use App\Models\LotMovement;
use App\Models\StockIn;
use App\Models\StockInItem;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

class StockInItemCorrectService
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {
    }

    /**
     * Admin-only correction of immutable fields on a finalized stock-in item.
     *
     * Correctable fields (all optional — only supplied fields are changed):
     *   - lot_number        (propagated to the linked Lot record; must remain globally unique)
     *   - manufacturing_date (propagated to the linked Lot record)
     *   - expiry_date       (propagated to the linked Lot record)
     *   - admin_reason      (required — recorded in audit log)
     *
     * The StockInItem's `scanned_lot_number` is updated in sync with the Lot.
     *
     * @param array<string, mixed> $data  Must include 'admin_reason' plus at least one correctable field.
     */
    public function correct(StockIn $stockIn, StockInItem $item, array $data, User $actor): StockInItem
    {
        return DB::transaction(function () use ($stockIn, $item, $data, $actor) {
            $stockIn = StockIn::query()->lockForUpdate()->findOrFail($stockIn->id);
            $item = StockInItem::query()->lockForUpdate()->findOrFail($item->id);

            if ($item->stock_in_id !== $stockIn->id) {
                throw new BusinessLogicException('Stock-in item does not belong to the provided session.');
            }

            // ----------------------------------------------------------------
            // 1. Guard: session must be finalized
            // ----------------------------------------------------------------
            if ($stockIn->status !== 'finalized') {
                throw new BusinessLogicException(
                    'Corrections can only be applied to finalized stock-in sessions.'
                );
            }

            // ----------------------------------------------------------------
            // 2. Guard: item must have an associated lot
            // ----------------------------------------------------------------
            $lotMultipliers = $this->getLotMultipliers($stockIn, $item);
            $lots = Lot::query()
                ->whereIn('id', array_keys($lotMultipliers))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $lot = $lots->get($item->lot_id);

            if ($lot === null) {
                throw new BusinessLogicException(
                    'This item has no associated lot record — cannot apply correction.'
                );
            }

            // ----------------------------------------------------------------
            // 3. Build change sets
            // ----------------------------------------------------------------
            $itemBefore = $item->toArray();
            $lotsBefore = $lots
                ->mapWithKeys(fn (Lot $relatedLot) => [$relatedLot->id => $relatedLot->toArray()])
                ->all();

            $itemChanges = [];
            $lotChanges  = [];

            if (array_key_exists('lot_number', $data)) {
                $newLotNumber = trim((string) $data['lot_number']);

                // Uniqueness check — allow the same lot_number (no-op)
                if ($newLotNumber !== $lot->lot_number) {
                    if (Lot::query()->where('lot_number', $newLotNumber)->where('id', '!=', $lot->id)->exists()) {
                        throw new BusinessLogicException(
                            "Lot number {$newLotNumber} already exists in inventory."
                        );
                    }
                    $itemChanges['scanned_lot_number'] = $newLotNumber;
                    $lotChanges['lot_number']          = $newLotNumber;
                }
            }

            if (array_key_exists('manufacturing_date', $data)) {
                if ($item->manufacturing_date?->toDateString() !== $data['manufacturing_date']) {
                    $itemChanges['manufacturing_date'] = $data['manufacturing_date'];
                    $lotChanges['manufacturing_date']  = $data['manufacturing_date'];
                }
            }

            if (array_key_exists('expiry_date', $data)) {
                if ($item->expiry_date?->toDateString() !== $data['expiry_date']) {
                    $itemChanges['expiry_date'] = $data['expiry_date'];
                    $lotChanges['expiry_date']  = $data['expiry_date'];
                }
            }

            $quantityDelta = 0;
            if (array_key_exists('quantity', $data)) {
                $newQuantity = (int) $data['quantity'];
                $quantityDelta = $newQuantity - (int) $item->quantity;

                if ($quantityDelta !== 0) {
                    $itemChanges['quantity'] = $newQuantity;
                }
            }

            if ($itemChanges === [] && $lotChanges === []) {
                throw new BusinessLogicException('No changes were supplied for this finalized stock-in item.');
            }

            $lotQuantityAdjustments = [];
            if ($quantityDelta !== 0) {
                foreach ($lotMultipliers as $lotId => $quantityPerReceivedItem) {
                    $quantityAdjustment = $quantityDelta * $quantityPerReceivedItem;
                    $relatedLot = $lots->get($lotId);

                    if ($relatedLot === null) {
                        throw new BusinessLogicException('A related inventory lot could not be found for this stock-in item.');
                    }

                    if ($quantityAdjustment < 0 && $relatedLot->quantity_available < abs($quantityAdjustment)) {
                        throw new BusinessLogicException(
                            "Cannot reduce received quantity because lot {$relatedLot->lot_number} no longer has enough available stock."
                        );
                    }

                    $lotQuantityAdjustments[$lotId] = $quantityAdjustment;
                }
            }

            // ----------------------------------------------------------------
            // 4. Apply changes and record the resulting inventory movements.
            // ----------------------------------------------------------------
            $item->fill($itemChanges);
            $lot->fill($lotChanges);

            $quantityMovements = [];
            foreach ($lotQuantityAdjustments as $lotId => $quantityAdjustment) {
                $relatedLot = $lots->get($lotId);
                $fromStatus = $relatedLot->status;
                $fromLocationType = $relatedLot->current_location_type;
                $fromLocationId = $relatedLot->current_location_id;

                $relatedLot->quantity += $quantityAdjustment;
                $relatedLot->quantity_available += $quantityAdjustment;

                if ($quantityAdjustment > 0 && $relatedLot->status === 'depleted') {
                    $relatedLot->status = 'available';
                    $relatedLot->current_location_type = 'warehouse';
                    $relatedLot->current_location_id = null;
                }

                $quantityMovements[] = [
                    'lot_id' => $relatedLot->id,
                    'movement_type' => $quantityAdjustment > 0
                        ? 'stock_in_correction_increase'
                        : 'stock_in_correction_decrease',
                    'reference_type' => StockInItem::class,
                    'reference_id' => $item->id,
                    'from_status' => $fromStatus,
                    'to_status' => $relatedLot->status,
                    'from_location_type' => $fromLocationType,
                    'from_location_id' => $fromLocationId,
                    'to_location_type' => $relatedLot->current_location_type,
                    'to_location_id' => $relatedLot->current_location_id,
                    'performed_at' => now(),
                    'performed_by_user_id' => $actor->id,
                    'remarks' => sprintf(
                        'Admin received-quantity correction for stock-in item %d (session %s). Reason: %s',
                        $item->id,
                        $stockIn->session_no,
                        $data['admin_reason']
                    ),
                    'quantity' => abs($quantityAdjustment),
                ];
            }

            $item->save();
            foreach ($lots as $relatedLot) {
                if ($relatedLot->isDirty()) {
                    $relatedLot->save();
                }
            }
            foreach ($quantityMovements as $movement) {
                LotMovement::query()->create($movement);
            }

            // ----------------------------------------------------------------
            // 5. Audit log — full before/after on both records
            // ----------------------------------------------------------------
            $this->auditLogService->logModelAction(
                auditableType: StockInItem::class,
                auditableId:   $item->id,
                actionType:    AuditAction::STOCK_IN_ITEM_UPDATED,
                actor:         $actor,
                description:   sprintf(
                    'Admin correction on stock-in item %d (session %s). Reason: %s',
                    $item->id,
                    $stockIn->session_no,
                    $data['admin_reason']
                ),
                before: [
                    'stock_in_item' => $itemBefore,
                    'lots'          => $lotsBefore,
                ],
                after: [
                    'stock_in_item' => $item->toArray(),
                    'lots'          => $lots
                        ->mapWithKeys(fn (Lot $relatedLot) => [$relatedLot->id => $relatedLot->toArray()])
                        ->all(),
                    'admin_reason'  => $data['admin_reason'],
                ],
            );

            return $item->refresh()->load(['product:id,ref_num,product_name', 'lot:id,lot_number,status,expiry_date,manufacturing_date']);
        });
    }

    /**
     * @return array<int, int> lot ID keyed by the quantity per received item
     */
    private function getLotMultipliers(StockIn $stockIn, StockInItem $item): array
    {
        if (! $item->isSetEntry()) {
            if ($item->lot_id === null) {
                throw new BusinessLogicException('This item has no associated lot record - cannot apply correction.');
            }

            return [(int) $item->lot_id => 1];
        }

        $componentLotSnapshot = collect($item->component_lots ?? []);
        if ($componentLotSnapshot->isNotEmpty() && $componentLotSnapshot->every(
            fn (array $componentLot) => isset($componentLot['lot_id'], $componentLot['quantity_per_set'])
        )) {
            $multipliers = [];

            foreach ($componentLotSnapshot as $componentLot) {
                $lotId = (int) $componentLot['lot_id'];
                $quantityPerSet = (int) $componentLot['quantity_per_set'];

                if ($lotId < 1 || $quantityPerSet < 1) {
                    throw new BusinessLogicException('The finalized instrument set lot snapshot is invalid.');
                }

                $multipliers[$lotId] = ($multipliers[$lotId] ?? 0) + $quantityPerSet;
            }

            return $multipliers;
        }

        $set = InstrumentSet::query()
            ->with('instrumentSetItems:id,instrument_set_id,product_id,quantity')
            ->find($item->instrument_set_id, ['id', 'set_code', 'set_name']);

        if ($set === null || $set->instrumentSetItems->isEmpty()) {
            throw new BusinessLogicException('The instrument set components for this stock-in item are unavailable.');
        }

        $componentLotsBySetItemId = collect($item->component_lots ?? [])
            ->keyBy(fn (array $componentLot) => (int) ($componentLot['instrument_set_item_id'] ?? 0));
        $multipliers = [];

        foreach ($set->instrumentSetItems as $setItem) {
            $componentLot = $componentLotsBySetItemId->get($setItem->id);
            $manualLotNumber = trim((string) ($componentLot['lot_number'] ?? ''));
            $isSystemGenerated = $componentLot === null || (bool) ($componentLot['generate_lot_number'] ?? false);

            $lotQuery = Lot::query()->where('product_id', $setItem->product_id);
            if ($isSystemGenerated) {
                $lotQuery
                    ->where('lot_number', 'like', $this->getGeneratedComponentLotPrefix($set, $setItem->product_id).'%')
                    ->whereHas('lotMovements', function ($query) use ($stockIn) {
                        $query
                            ->where('movement_type', 'stock_in')
                            ->where('reference_type', StockIn::class)
                            ->where('reference_id', $stockIn->id);
                    });
            } else {
                $lotQuery->where('lot_number', $manualLotNumber);
            }

            $relatedLot = $lotQuery->first();
            if ($relatedLot === null) {
                throw new BusinessLogicException(
                    "The related component lot could not be found for instrument set {$set->set_name}."
                );
            }

            $multipliers[$relatedLot->id] = ($multipliers[$relatedLot->id] ?? 0) + (int) $setItem->quantity;
        }

        return $multipliers;
    }

    private function getGeneratedComponentLotPrefix(InstrumentSet $set, int $productId): string
    {
        $codePart = $set->set_code !== null && $set->set_code !== ''
            ? preg_replace('/[^A-Z0-9_-]+/', '', strtoupper((string) $set->set_code))
            : 'SET'.$set->id;

        return sprintf('COMP-%s-P%d-', $codePart, $productId);
    }
}
