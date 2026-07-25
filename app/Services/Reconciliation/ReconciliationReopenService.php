<?php

namespace App\Services\Reconciliation;

use App\Enums\AuditAction;
use App\Exceptions\BusinessLogicException;
use App\Models\Lot;
use App\Models\LotMovement;
use App\Models\Reconciliation;
use App\Models\ReconciliationItem;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

class ReconciliationReopenService
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {
    }

    /**
     * Reopen a finalized reconciliation.
     *
     * - Reverts every reconciliation movement so each lot returns to its
     *   pre-finalization balance, status, and location.
     * - Removes the reversed movements so re-finalization starts from a clean slate.
     * - Deletes all reconciliation items so finalization can recompute cleanly.
     * - Clears completion timestamps; stores reopen reason/actor/timestamp.
     */
    public function reopen(Reconciliation $reconciliation, string $reason, User $actor): Reconciliation
    {
        return DB::transaction(function () use ($reconciliation, $reason, $actor) {
            /** @var Reconciliation $locked */
            $locked = Reconciliation::query()
                ->lockForUpdate()
                ->findOrFail($reconciliation->id);

            if ($locked->status !== 'finalized') {
                throw new BusinessLogicException(
                    "Only finalized reconciliations can be reopened (current: {$locked->status})."
                );
            }

            // ----------------------------------------------------------------
            // 1. Reverse every movement produced by finalization. Reverse order
            //    is essential when one lot has several outcomes (for example,
            //    partially returned and partially used).
            // ----------------------------------------------------------------
            $movements = LotMovement::query()
                ->where('reference_type', Reconciliation::class)
                ->where('reference_id', $locked->id)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();

            foreach ($movements as $movement) {
                /** @var Lot|null $lot */
                $lot = Lot::query()->lockForUpdate()->find($movement->lot_id);

                if ($lot) {
                    match ($movement->movement_type) {
                        'returned' => $lot->fill([
                            'quantity_available' => $lot->quantity_available - $movement->quantity,
                            'quantity_consigned' => $lot->quantity_consigned + $movement->quantity,
                        ]),
                        'used', 'damaged', 'missing' => $lot->fill([
                            'quantity_consigned' => $lot->quantity_consigned + $movement->quantity,
                        ]),
                        default => null,
                    };

                    $lot->fill([
                        'status'                => $movement->from_status,
                        'current_location_type' => $movement->from_location_type,
                        'current_location_id'   => $movement->from_location_id,
                    ])->save();
                }

                $movement->delete();
            }

            // ----------------------------------------------------------------
            // 2. Remove all reconciliation items (clean slate for re-finalization)
            // ----------------------------------------------------------------
            ReconciliationItem::query()
                ->where('reconciliation_id', $locked->id)
                ->delete();

            // ----------------------------------------------------------------
            // 3. Mark reconciliation as reopened
            // ----------------------------------------------------------------
            $locked->fill([
                'status'               => 'reopened',
                'completed_at'         => null,
                'completed_by_user_id' => null,
                'reopened_at'          => now(),
                'reopened_by_user_id'  => $actor->id,
                'reopen_reason'        => $reason,
            ])->save();

            $this->auditLogService->logModelAction(
                auditableType: Reconciliation::class,
                auditableId:   $locked->id,
                actionType:    AuditAction::RECONCILIATION_REOPENED,
                actor:         $actor,
                description:   "Reconciliation {$locked->reconciliation_no} reopened. Reason: {$reason}",
                after: [
                    'status'        => 'reopened',
                    'reopened_at'   => now()->toIso8601String(),
                    'reopen_reason' => $reason,
                ],
            );

            return $locked->refresh()->load([
                'consignment:id,consignment_no',
                'returnSession:id,return_session_no',
                'picUser:id,full_name',
                'reopenedByUser:id,full_name',
            ]);
        });
    }
}
