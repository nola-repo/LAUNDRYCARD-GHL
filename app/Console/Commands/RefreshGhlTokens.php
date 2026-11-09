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
                            {--dry-run : Show what would be refreshed without making any changes}
                            {--location= : Refresh one specific GHL location ID only}';

    protected $description = 'Proactively refresh GHL OAuth tokens that are about to expire.';

    public function handle(HighLevelOAuthService $oauth): int
    {
        $leeway = (int) config('integration.ghl.refresh_leeway');
        $dryRun = (bool) $this->option('dry-run');

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

        if ($dryRun) {
            $this->warn('[dry-run] No changes will be made.');
        }

        $this->line("Checking {$connections->count()} connection(s)...");

        $refreshed = 0;
        $skipped   = 0;
        $failed    = 0;

        foreach ($connections as $connection) {
            $label = "{$connection->location_id} ({$connection->location_name})";

            if ($dryRun) {
                $state = $connection->tokenExpiryState();
                $this->line("  [dry-run] {$label} — current state: {$state}, expires: {$connection->expires_at}");
                $skipped++;
                continue;
            }

            try {
                // Use the returned connection — ensureFresh() may return a
                // refreshed model and the return value must not be discarded.
                $fresh = $oauth->ensureFresh($connection);
                // Reload from DB to confirm persisted state.
                $fresh->refresh();

                $state = $fresh->tokenExpiryState();

                if ($state === 'valid') {
                    $this->line("  ✓ {$label} — valid until {$fresh->expires_at}");
                    $refreshed++;
                } else {
                    $this->warn("  ? {$label} — still {$state} after refresh (expires: {$fresh->expires_at})");
                    $skipped++;
                }
            } catch (Throwable $e) {
                $this->error("  ✗ {$label} — " . $e->getMessage());
                $failed++;
            }
        }

        $this->newLine();
        $this->line("Done — refreshed: {$refreshed}, skipped: {$skipped}, failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}

