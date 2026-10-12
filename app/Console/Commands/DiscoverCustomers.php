<?php

namespace App\Console\Commands;

use App\Services\CustomerDiscoveryService;
use Illuminate\Console\Command;

class DiscoverCustomers extends Command
{
    protected $signature = 'integration:discover {--read-only : Scan and report only; persist nothing, queue nothing} {--store= : Limit to one LaundryCard store id}';

    protected $description = 'Poll LaundryCard stores for new customers and queue verified-branch records for GHL sync.';

    public function handle(CustomerDiscoveryService $discovery): int
    {
        $run = $discovery->run((bool) $this->option('read-only'), 'schedule', null, $this->option('store'));
        $stats = $run->stats ?? [];

        $this->info("Discovery {$run->status} (mode: {$run->mode})");
        foreach (['stores_scanned', 'stores_failed', 'cards_seen', 'new_records', 'baseline_records', 'confirmed_new', 'held', 'queued'] as $k) {
            $this->line(str_pad($k, 18).($stats[$k] ?? 0));
        }
        if ($run->error) {
            $this->error($run->error);
        }

        return in_array($run->status, ['completed', 'partial'], true) ? self::SUCCESS : self::FAILURE;
    }
}
