<?php

namespace App\Services\SupplierReturn;

use App\Enums\AuditAction;
use App\Exceptions\BusinessLogicException;
use App\Models\Lot;
use App\Models\LotMovement;
use App\Models\SupplierReturn;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

class SupplierReturnReopenService
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {
    }

    /**
     * Reopen a completed supplier return so an administrator can correct it.
     * Later activity on an affected lot blocks the reopen to preserve the
     * integrity of inventory balances and locations.
     */
    public function reopen(SupplierReturn $supplierReturn, string $reason, User $actor): SupplierReturn
    {
        return DB::transaction(function () use ($supplierReturn, $reason, $actor) {
            $locked = SupplierReturn::query()->lockForUpdate()->findOrFail($supplierReturn->id);

            if ($locked->status !== 'completed') {
                throw new BusinessLogicException('Only completed supplier returns can be reopened.');
            }

            $movements = LotMovement::query()
                ->where('reference_type', SupplierReturn::class)
                ->where('reference_id', $locked->id)
                ->where('movement_type', 'returned_to_supplier')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();

            if ($movements->isEmpty()) {
                throw new BusinessLogicException('This completed supplier return has no inventory movements to reverse.');
            }

            $movementIds = $movements->pluck('id')->all();
            $before = $locked->toArray();
            $reversedMovements = $movements->map(fn (LotMovement $movement) => $movement->toArray())->all();

            foreach ($movements as $movement) {
                // Lock before inspecting movement history so another inventory
                // writer cannot add a movement for this lot mid-reopen.
                $lot = Lot::query()->lockForUpdate()->findOrFail($movement->lot_id);

                $hasLaterMovement = LotMovement::query()
                    ->where('lot_id', $movement->lot_id)
                    ->where('id', '>', $movement->id)
                    ->whereNotIn('id', $movementIds)
                    ->exists();

                if ($hasLaterMovement) {
                    throw new BusinessLogicException(
                        'This supplier return cannot be reopened because a related lot has later inventory activity.'
                    );
                }

                $lot->quantity_available += $movement->quantity;
                $lot->status = $movement->from_status;
                $lot->current_location_type = $movement->from_location_type;
                $lot->current_location_id = $movement->from_location_id;
                $lot->save();

                $movement->delete();
            }

            $locked->fill([
                'status' => 'draft',
                'completed_at' => null,
                'completed_by_user_id' => null,
            ])->save();

            $this->auditLogService->logModelAction(
                auditableType: SupplierReturn::class,
                auditableId: $locked->id,
                actionType: AuditAction::SUPPLIER_RETURN_REOPENED,
                actor: $actor,
                description: "Supplier return {$locked->supplier_return_no} reopened. Reason: {$reason}",
                before: [
                    'supplier_return' => $before,
                    'lot_movements' => $reversedMovements,
                ],
                after: [
                    'supplier_return' => $locked->toArray(),
                    'reopen_reason' => $reason,
                ],
            );

            return $locked->refresh();
        });
    }
}
