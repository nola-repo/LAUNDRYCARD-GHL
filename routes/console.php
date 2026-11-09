<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Poll LaundryCard for new customers (no webhook exists). Overlap-protected.
Schedule::command('integration:discover')
    ->cron('*/'.max(1, (int) config('integration.discovery.schedule_every_minutes')).' * * * *')
    ->withoutOverlapping(30)
    ->onOneServer();

// Proactively rotate GHL OAuth tokens before they expire.
// Runs every 6 hours; tokens expire every 24 h so this provides 4 refresh
// opportunities per day, with ensureFresh() leeway (300 s) as the final guard
// on each live API call.
Schedule::command('ghl:refresh-tokens')
    ->everySixHours()
    ->withoutOverlapping(5)
    ->onOneServer()
    ->runInBackground();
