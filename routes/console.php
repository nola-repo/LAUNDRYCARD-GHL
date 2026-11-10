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

// POS Account enrichment for anonymous records (requires LAUNDRYCARD_POS_MACH_NO_* to be set).
if (config('integration.discovery.enrich_with_account_detail')) {
    Schedule::command('integration:enrich')
        ->everyFifteenMinutes()
        ->withoutOverlapping(10)
        ->onOneServer();
}

// Proactively rotate GHL OAuth tokens before they expire.
// Runs every 6 hours; tokens expire every 24 h so this provides 4 refresh
// opportunities per day, with ensureFresh() leeway (300 s) as the final guard
// on each live API call.
Schedule::command('ghl:refresh-tokens')
    ->everySixHours()
    ->withoutOverlapping(5)
    ->onOneServer()
    ->runInBackground();

// Incremental transaction ingestion — disabled by default until API field names
// and transaction type codes have been confirmed from a live response.
// Enable via INTEGRATION_TRANSACTION_INGESTION_ENABLED=true.
if (config('integration.transactions.enabled')) {
    Schedule::command('integration:ingest-transactions')
        ->cron('*/'.max(1, (int) config('integration.transactions.schedule_every_minutes')).' * * * *')
        ->withoutOverlapping(10)
        ->onOneServer();
}
