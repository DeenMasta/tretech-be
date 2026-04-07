<?php

namespace App\Services\UsageSummary;

use App\Enums\AuditAction;
use App\Exceptions\BusinessLogicException;
use App\Jobs\PushUsageSummaryJob;
use App\Models\UsageSummary;
use App\Models\User;
use App\Services\Audit\AuditLogService;

class UsageSummaryPushService
{
    public function __construct(
        private readonly ErpPushService  $erpPushService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * Dispatch a queued push job for the given usage summary.
     * The job handles retry logic via ErpPushService.
     */
    public function dispatchPush(UsageSummary $summary, User $actor): void
    {
        if (!in_array($summary->status, ['generated', 'push_failed'], true)) {
            throw new BusinessLogicException(
                "Usage summary must be in 'generated' or 'push_failed' status to push (current: {$summary->status})."
            );
        }

        // Update status to indicate a push is in flight
        $summary->update(['status' => 'push_pending']);

        PushUsageSummaryJob::dispatch($summary->id, $actor->id);

        $this->auditLogService->logModelAction(
            auditableType: UsageSummary::class,
            auditableId:   $summary->id,
            actionType:    AuditAction::USAGE_SUMMARY_PUSHED,
            actor:         $actor,
            description:   "ERP push dispatched for usage summary {$summary->summary_no}.",
            after: ['status' => 'push_pending'],
        );
    }

    /**
     * Execute a synchronous push (used by the retry job).
     * Updates the summary status based on the outcome.
     */
    public function executePush(UsageSummary $summary, ?int $pushedByUserId = null): void
    {
        $log = $this->erpPushService->push($summary, $pushedByUserId);

        // Update summary status
        $newStatus = match ($log->status) {
            'success'          => 'pushed',
            'failed_retryable' => 'push_pending', // scheduler will retry
            default            => 'push_failed',
        };

        $summary->update(['status' => $newStatus]);
    }
}
