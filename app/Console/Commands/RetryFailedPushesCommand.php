<?php

namespace App\Console\Commands;

use App\Models\UsageSummary;
use App\Models\UsageSummaryPushLog;
use App\Services\UsageSummary\UsageSummaryPushService;
use Illuminate\Console\Command;

class RetryFailedPushesCommand extends Command
{
    protected $signature   = 'tretech:retry-failed-pushes';
    protected $description = 'Retry ERP pushes for usage summaries with failed_retryable status.';

    public function __construct(
        private readonly UsageSummaryPushService $pushService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('[' . now()->format('Y-m-d H:i') . '] Checking for retryable ERP pushes...');

        // Find summaries that have a retryable push log with next_retry_at in the past
        $retryableLogs = UsageSummaryPushLog::query()
            ->where('status', 'failed_retryable')
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now())
            ->with('usageSummary')
            ->get();

        if ($retryableLogs->isEmpty()) {
            $this->info('  No pending retries found.');
            return self::SUCCESS;
        }

        $this->info("  Found {$retryableLogs->count()} push(es) to retry.");

        $summaryIds = $retryableLogs->pluck('usage_summary_id')->unique();

        foreach ($summaryIds as $summaryId) {
            /** @var UsageSummary|null $summary */
            $summary = UsageSummary::find($summaryId);

            if (!$summary || !in_array($summary->status, ['push_pending', 'push_failed', 'generated'], true)) {
                continue;
            }

            try {
                $this->pushService->executePush($summary, pushedByUserId: null);
                $this->info("  Summary #{$summaryId} ({$summary->summary_no}): push executed.");
            } catch (\Throwable $e) {
                $this->error("  Summary #{$summaryId} push error: {$e->getMessage()}");
            }
        }

        $this->info('Retry run complete.');

        return self::SUCCESS;
    }
}
