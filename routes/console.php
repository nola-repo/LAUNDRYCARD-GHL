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
