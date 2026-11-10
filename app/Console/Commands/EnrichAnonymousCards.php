<?php

namespace App\Console\Commands;

use App\Services\EnrichmentService;
use Illuminate\Console\Command;

class EnrichAnonymousCards extends Command
{
    protected $signature = 'integration:enrich
                            {--store= : Only enrich a specific store ID}
                            {--dry-run : Report what would be enriched without making API calls}';

    protected $description = 'Enrich anonymous LaundryCard records via the POS Account endpoint';

    public function handle(EnrichmentService $svc): int
    {
        if ($this->option('dry-run')) {
            $this->warn('DRY-RUN mode: no API calls will be made.');
            $this->showDryRunReport();

            return self::SUCCESS;
        }

        if (! config('integration.discovery.enrich_with_account_detail')) {
            $this->warn('POS Account enrichment is disabled (INTEGRATION_ENRICH_ACCOUNT=false).');

            return self::SUCCESS;
        }

        $storeId = $this->option('store');
        $this->info('Running POS Account enrichment' . ($storeId ? " for store {$storeId}" : ' for all stores') . '...');

        $summary = $svc->run($storeId ?: null);

        if (empty($summary)) {
            $this->warn('No stores processed.');

            return self::SUCCESS;
        }

        $headers = ['Store', 'Enriched', 'Skipped', 'Failed', 'No MachNo'];
        $rows = [];
        foreach ($summary as $sid => $stats) {
            $rows[] = [$sid, $stats['enriched'], $stats['skipped'], $stats['failed'], $stats['no_mach'] ? 'YES' : 'no'];
        }
        $this->table($headers, $rows);

        $total = array_sum(array_column($summary, 'enriched'));
        $this->info("Total enriched: {$total}");

        return self::SUCCESS;
    }

    private function showDryRunReport(): void
    {
        $lc = app(\App\Services\LaundryCardClient::class);
        $diag = $lc->configDiagnostics();

        $this->line('--- Configuration Diagnostics ---');
        $this->line('LaundryCard configured: ' . ($diag['configured'] ? 'YES' : 'NO'));
        $this->line('Enrichment enabled:     ' . ($diag['enrichment_enabled'] ? 'YES' : 'NO'));
        $this->newLine();
        $this->line('Per-store MachNo status:');
        foreach ($diag['pos_mach_per_store'] as $store => $status) {
            $icon = $status === 'missing' ? '❌' : '✅';
            $this->line("  {$icon} Store {$store}: {$status}");
        }

        $anonymous = \App\Models\LaundrycardSourceRecord::query()
            ->where(function ($q) {
                $q->whereNull('customer_name')
                    ->orWhereNull('customer_email')
                    ->orWhereNull('customer_phone');
            })
            ->whereNotIn('processing_status', [
                \App\Models\LaundrycardSourceRecord::SYNCED_CREATED,
                \App\Models\LaundrycardSourceRecord::SYNCED_UPDATED,
            ])
            ->count();

        $this->newLine();
        $this->line("Anonymous records eligible for enrichment: {$anonymous}");
    }
}

