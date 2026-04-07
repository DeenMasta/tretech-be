<?php

namespace App\Services\SupplierReturn;

use App\Enums\AuditAction;
use App\Exceptions\BusinessLogicException;
use App\Models\Lot;
use App\Models\LotMovement;
use App\Models\SupplierReturn;
use App\Models\SupplierReturnItem;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

class SupplierReturnCompleteService
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {
    }

    /**
     * Complete a draft supplier return atomically:
     *   - Validates at least one item exists
     *   - Sets each lot status to `returned_to_supplier`
     *   - Creates a LotMovement per lot
     *   - Marks supplier return as `completed`
     *   - Writes audit log
     */
    public function complete(SupplierReturn $supplierReturn, User $actor): SupplierReturn
    {
        return DB::transaction(function () use ($supplierReturn, $actor) {
            /** @var SupplierReturn $locked */
            $locked = SupplierReturn::query()
                ->lockForUpdate()
                ->findOrFail($supplierReturn->id);

            if ($locked->status !== 'draft') {
                throw new BusinessLogicException(
                    "Only draft supplier returns can be completed (current status: {$locked->status})."
                );
            }

            $items = SupplierReturnItem::query()
                ->where('supplier_return_id', $locked->id)
                ->get();

            if ($items->isEmpty()) {
                throw new BusinessLogicException('Cannot complete a supplier return with no items.');
            }

            foreach ($items as $item) {
                /** @var Lot $lot */
                $lot = Lot::query()->lockForUpdate()->findOrFail($item->lot_id);

                if (in_array($lot->status, ['disposed', 'returned_to_supplier'], true)) {
                    throw new BusinessLogicException(
                        "Lot [{$lot->lot_number}] is already {$lot->status}; cannot return to supplier."
                    );
                }

                LotMovement::query()->create([
                    'lot_id'               => $lot->id,
                    'movement_type'        => 'returned_to_supplier',
                    'reference_type'       => SupplierReturn::class,
                    'reference_id'         => $locked->id,
                    'from_status'          => $lot->status,
                    'to_status'            => 'returned_to_supplier',
                    'from_location_type'   => $lot->current_location_type,
                    'from_location_id'     => $lot->current_location_id,
                    'to_location_type'     => null,
                    'to_location_id'       => null,
                    'performed_at'         => now(),
                    'performed_by_user_id' => $actor->id,
                    'remarks'              => "Returned to supplier via {$locked->supplier_return_no}: {$item->return_reason}",
                ]);

                $lot->fill([
                    'status'               => 'returned_to_supplier',
                    'current_location_type' => null,
                    'current_location_id'   => null,
                ])->save();
            }

            $locked->fill([
                'status'               => 'completed',
                'completed_at'         => now(),
                'completed_by_user_id' => $actor->id,
            ])->save();

            $this->auditLogService->logEloquent(
                model:       $locked,
                actionType:  AuditAction::SUPPLIER_RETURN_COMPLETED,
                actor:       $actor,
                description: "Supplier return {$locked->supplier_return_no} completed — {$items->count()} lot(s) returned.",
                after:       ['item_count' => $items->count()],
            );

            return $locked->refresh();
        });
    }
}
