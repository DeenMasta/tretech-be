<?php

namespace App\Jobs;

use App\Models\UsageSummary;
use App\Services\UsageSummary\UsageSummaryPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\ThrottlesExceptions;

class PushUsageSummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Maximum total attempts before the job is marked failed */
    public int $tries = 3;

    /** Seconds to wait between automatic Laravel queue retries (exponential via middleware) */
    public int $backoff = 30;

    public function __construct(
        private readonly int  $usageSummaryId,
        private readonly ?int $actor_user_id = null,
    ) {
    }

    /**
     * Throttle and retry on exceptions: allow 1 failure per interval, release back to queue.
     */
    public function middleware(): array
    {
        return [
            (new ThrottlesExceptions(1, 5))->backoff(15),
        ];
    }

    public function handle(UsageSummaryPushService $pushService): void
    {
        $summary = UsageSummary::find($this->usageSummaryId);

        if (!$summary) {
            // Silently discard — record was deleted
            return;
        }

        $pushService->executePush($summary, $this->actor_user_id);
    }

    /**
     * Called when all retries are exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        $summary = UsageSummary::find($this->usageSummaryId);
        $summary?->update(['status' => 'push_failed']);
    }
}
