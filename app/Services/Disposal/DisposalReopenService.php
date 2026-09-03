<?php

namespace App\Services\Disposal;

use App\Enums\AuditAction;
use App\Exceptions\BusinessLogicException;
use App\Models\Disposal;
use App\Models\Lot;
use App\Models\LotMovement;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

class DisposalReopenService
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {
    }

    /**
     * Reopen a completed disposal so an administrator can correct its draft.
     *
     * The original lot movements are reversed and removed only when no later
     * inventory movement has affected the same lot. This prevents reopening a
     * historical disposal from overwriting newer inventory state.
     */
    public function reopen(Disposal $disposal, string $reason, User $actor): Disposal
    {
        return DB::transaction(function () use ($disposal, $reason, $actor) {
            $locked = Disposal::query()->lockForUpdate()->findOrFail($disposal->id);

            if ($locked->status !== 'completed') {
                throw new BusinessLogicException('Only completed disposals can be reopened.');
            }

            $movements = LotMovement::query()
                ->where('reference_type', Disposal::class)
                ->where('reference_id', $locked->id)
                ->where('movement_type', 'disposed')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();

            if ($movements->isEmpty()) {
                throw new BusinessLogicException('This completed disposal has no inventory movements to reverse.');
            }

            $movementIds = $movements->pluck('id')->all();
            $before = $locked->toArray();
            $reversedMovements = $movements->map(fn (LotMovement $movement) => $movement->toArray())->all();

            foreach ($movements as $movement) {
                // Take the lot lock before checking its movement history. All
                // inventory writers lock the lot, so this closes the gap in
                // which a new movement could otherwise be posted while this
                // reopen is in progress.
                $lot = Lot::query()->lockForUpdate()->findOrFail($movement->lot_id);

                $hasLaterMovement = LotMovement::query()
                    ->where('lot_id', $movement->lot_id)
                    ->where('id', '>', $movement->id)
                    ->whereNotIn('id', $movementIds)
                    ->exists();

                if ($hasLaterMovement) {
                    throw new BusinessLogicException(
                        'This disposal cannot be reopened because a related lot has later inventory activity.'
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
                auditableType: Disposal::class,
                auditableId: $locked->id,
                actionType: AuditAction::DISPOSAL_REOPENED,
                actor: $actor,
                description: "Disposal {$locked->disposal_no} reopened. Reason: {$reason}",
                before: [
                    'disposal' => $before,
                    'lot_movements' => $reversedMovements,
                ],
                after: [
                    'disposal' => $locked->toArray(),
                    'reopen_reason' => $reason,
                ],
            );

            return $locked->refresh();
        });
    }
}
