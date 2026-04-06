<?php

namespace App\Services\Reconciliation;

use App\Enums\AuditAction;
use App\Exceptions\BusinessLogicException;
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
     * - Reverts used lots back to `supplied` so they remain under the consignment.
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
            // 1. Revert used lots back to `supplied`
            //    (They still belong to the consignment pending re-finalization)
            // ----------------------------------------------------------------
            $usedItems = ReconciliationItem::query()
                ->with('lot')
                ->where('reconciliation_id', $locked->id)
                ->where('result', 'used')
                ->get();

            foreach ($usedItems as $item) {
                if ($item->lot) {
                    $item->lot->fill(['status' => 'supplied'])->save();
                }
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
