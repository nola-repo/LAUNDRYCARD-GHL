<?php

namespace App\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\IntegrationLogger::class);
        $this->app->singleton(\App\Services\LaundryCardClient::class);

        // Guarantee the Contact Sync Engine and Transaction Ingestion are always
        // enabled at the application level regardless of how Vercel injects
        // environment variables into the serverless runtime. Any explicit
        // INTEGRATION_CONTACT_SYNC_ENABLED=false in the Vercel dashboard would
        // suppress putenv() overrides in api/index.php because Vercel injects
        // vars before PHP spawns — but Config::set() runs AFTER the config is
        // cached and therefore always wins.
        Config::set('integration.sync.enabled', true);
        Config::set('integration.transactions.enabled', true);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') === 'production' || str_starts_with(config('app.url', ''), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
