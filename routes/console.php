<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// -------------------------------------------------------------------------
// TRETECH Scheduled Tasks
// -------------------------------------------------------------------------

// Daily 08:00 — check lots expiring within 30/60/90 days and log alert counts
Schedule::command('tretech:check-expiry')->dailyAt('08:00');

// Every 15 minutes — retry failed ERP pushes that have a next_retry_at in the past
Schedule::command('tretech:retry-failed-pushes')->everyFifteenMinutes();
