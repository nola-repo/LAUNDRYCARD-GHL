<?php

namespace App\Console\Commands;

use App\Models\GhlConnection;
use App\Services\HighLevelOAuthService;
use Illuminate\Console\Command;
use Throwable;

class RefreshGhlTokens extends Command
{
    protected $signature = 'ghl:refresh-tokens
                            {--force : Refresh all active connections, even valid ones}
                            {--location= : Refresh one specific GHL location ID only}';

    protected $description = 'Proactively refresh GHL OAuth tokens that are about to expire.';

    public function handle(HighLevelOAuthService $oauth): int
    {
        $leeway = (int) config('integration.ghl.refresh_leeway');

        // Proactive window: 2× the per-request leeway so the scheduler catches
        // tokens well before ensureFresh() would have to do it on a live request.
        $windowSeconds = $leeway * 2;

        $query = GhlConnection::where('status', GhlConnection::ACTIVE)
            ->whereNotNull('refresh_token');

        if ($this->option('location')) {
            $query->where('location_id', $this->option('location'));
        } elseif (! $this->option('force')) {
            // Only tokens that are expired OR within the proactive window.
            $query->where(function ($q) use ($windowSeconds) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '<=', now()->addSeconds($windowSeconds));
            });
        }

        $connections = $query->get();

        if ($connections->isEmpty()) {
            $this->line('No tokens need refreshing.');

            return self::SUCCESS;
        }

        $this->line("Checking {$connections->count()} connection(s)...");

        $refreshed = 0;
        $skipped   = 0;
        $failed    = 0;

        foreach ($connections as $connection) {
            try {
                $oauth->ensureFresh($connection);
                $connection->refresh(); // reload from DB after potential lock-based refresh

                $state = $connection->tokenExpiryState();

                if ($state === 'valid') {
                    $this->line("  ✓ {$connection->location_id} ({$connection->location_name}) — {$state}");
                    $refreshed++;
                } else {
                    $this->warn("  ? {$connection->location_id} — still {$state} after refresh attempt");
                    $skipped++;
                }
            } catch (Throwable $e) {
                $this->error("  ✗ {$connection->location_id} — {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->line("Done — refreshed: {$refreshed}, skipped: {$skipped}, failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}

