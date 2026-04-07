<?php

namespace App\Console\Commands;

use App\Models\Lot;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CheckExpiryCommand extends Command
{
    protected $signature   = 'tretech:check-expiry';
    protected $description = 'Flag lots expiring within 30, 60, and 90 days and report counts.';

    public function handle(): int
    {
        $now     = Carbon::today();
        $windows = [30, 60, 90];

        $this->info('[' . now()->format('Y-m-d H:i') . '] Running expiry check...');

        foreach ($windows as $days) {
            $count = Lot::query()
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '>=', $now)
                ->whereDate('expiry_date', '<=', $now->copy()->addDays($days))
                ->whereIn('status', ['available', 'supplied', 'holding'])
                ->count();

            $this->info("  Within {$days} days: {$count} lot(s) expiring.");
        }

        // Warn about already-expired active lots
        $expired = Lot::query()
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', $now)
            ->whereIn('status', ['available', 'supplied'])
            ->count();

        if ($expired > 0) {
            $this->warn("  ALERT: {$expired} lot(s) are past expiry and still active!");
        }

        $this->info('Expiry check complete.');

        return self::SUCCESS;
    }
}
