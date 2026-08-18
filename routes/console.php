<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// No application-defined scheduled business tasks exist in this codebase —
// confirmed by audit, and none are invented here without a requirement.
// This is Laravel's own built-in maintenance command only: prune failed_jobs
// rows older than a week so that table doesn't grow forever. It runs in the
// landlord/central context (the scheduler has no tenant initialized), so it
// only prunes the landlord database's failed_jobs table — each tenant also
// has its own failed_jobs table (see docs/PRODUCTION_OPERATIONS.md) that
// this does NOT reach; tenant-scoped pruning would need to iterate every
// tenant via tenancy()->runForMultiple(...) and is intentionally left as a
// documented follow-up rather than guessed at here.
//
// This task only runs at all if something actually invokes the scheduler —
// production needs either a cron entry running `php artisan schedule:run`
// every minute, or a long-running `php artisan schedule:work` process (see
// docs/PRODUCTION_OPERATIONS.md). Neither exists in compose.yaml today.
Schedule::command('queue:prune-failed', ['--hours' => 168])->daily();
