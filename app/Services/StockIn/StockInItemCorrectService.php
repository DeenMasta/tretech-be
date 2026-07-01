<?php

namespace App\Services\StockIn;

use App\Enums\AuditAction;
use App\Exceptions\BusinessLogicException;
use App\Models\Lot;
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
            $lot = Lot::query()->lockForUpdate()->find($item->lot_id);

            if ($lot === null) {
                throw new BusinessLogicException(
                    'This item has no associated lot record — cannot apply correction.'
                );
            }

            // ----------------------------------------------------------------
            // 3. Build change sets
            // ----------------------------------------------------------------
            $itemBefore = $item->toArray();
            $lotBefore  = $lot->toArray();

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
                $itemChanges['manufacturing_date'] = $data['manufacturing_date'];
                $lotChanges['manufacturing_date']  = $data['manufacturing_date'];
            }

            if (array_key_exists('expiry_date', $data)) {
                $itemChanges['expiry_date'] = $data['expiry_date'];
                $lotChanges['expiry_date']  = $data['expiry_date'];
            }

            if (empty($lotChanges)) {
                throw new BusinessLogicException(
                    'No correctable fields supplied. Provide at least one of: lot_number, manufacturing_date, expiry_date.'
                );
            }

            // ----------------------------------------------------------------
            // 4. Apply changes
            // ----------------------------------------------------------------
            $item->fill($itemChanges)->save();
            $lot->fill($lotChanges)->save();

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
                    'lot'           => $lotBefore,
                ],
                after: [
                    'stock_in_item' => $item->toArray(),
                    'lot'           => $lot->toArray(),
                    'admin_reason'  => $data['admin_reason'],
                ],
            );

            return $item->refresh()->load(['product:id,ref_num,product_name', 'lot:id,lot_number,status,expiry_date,manufacturing_date']);
        });
    }
}
