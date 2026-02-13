<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule the CleanTempZips command to run daily at 02:00
Schedule::command('clean:temp-zips --older-than=24')->dailyAt('02:00');

// Communications indexing cadence:
// - headers-only frequently for fast inbox freshness
// - full poll less frequently for deep body/attachment mirroring
Schedule::command('communications:sync-headers --limit=200')->everyTwoMinutes();
Schedule::command('communications:poll --limit=50')->everyTenMinutes();
