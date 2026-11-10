<?php

namespace App\Console\Commands;

use App\Services\TransactionDiscoveryService;
use Illuminate\Console\Command;

class IngestTransactions extends Command
{
    protected $signature = 'integration:ingest-transactions
                            {--store= : Only ingest for a specific store ID}
                            {--backfill-hours= : Pull this many hours of history (windowed, cursor untouched, safe to re-run)}
                            {--dry-run : Show what would be ingested without making API calls}';

    protected $description = 'Incrementally ingest LaundryCard transactions from /api/transact';

    public function handle(TransactionDiscoveryService $svc): int
    {
        if ($this->option('dry-run')) {
            return $this->dryRun();
        }

        if (! config('integration.transactions.enabled')) {
            $this->warn('Transaction ingestion is disabled (INTEGRATION_TRANSACTION_INGESTION_ENABLED=false).');
            $this->line('Set INTEGRATION_TRANSACTION_INGESTION_ENABLED=true to enable.');

            return self::SUCCESS;
        }

        if ($hours = (int) $this->option('backfill-hours')) {
            return $this->backfill($svc, $hours);
        }

        $storeId = $this->option('store');
        $this->info('Running transaction ingestion' . ($storeId ? " for store {$storeId}" : ' for all stores') . '...');

        $result = $svc->run($storeId ?: null, 'manual');

        if (isset($result['skipped'])) {
            $this->warn("Skipped: {$result['skipped']}");

            return self::SUCCESS;
        }

        $headers = ['Store', 'Inserted', 'Duplicates', 'Raw Count', 'Cursor Advanced', 'Fields Confirmed'];
        $rows = [];
        foreach ($result['stores'] as $storeId => $stats) {
            if (isset($stats['error'])) {
                $rows[] = [$storeId, 'ERROR', '-', '-', '-', $stats['message']];
                continue;
            }
            $rows[] = [
                $storeId,
                $stats['inserted'] ?? 0,
                $stats['duplicate'] ?? 0,
                $stats['raw_count'] ?? 0,
                ($stats['cursor_advanced'] ?? false) ? 'YES' : 'no',
                ($stats['fields_confirmed'] ?? false) ? 'YES' : '⚠️  UNCONFIRMED',
            ];
        }

        $this->table($headers, $rows);

        $unconfirmedStores = collect($result['stores'])
            ->filter(fn ($s) => ! ($s['fields_confirmed'] ?? true))
            ->keys();

        if ($unconfirmedStores->isNotEmpty()) {
            $this->warn('');
            $this->warn('⚠️  Transaction field names have NOT been confirmed for: ' . $unconfirmedStores->join(', '));
            $this->warn('   The API returned no rows, or no recognisable fields were found.');
            $this->warn('   Inspect raw payloads and update TransactionDiscoveryService::TYPE_MAP.');
        }

        return self::SUCCESS;
    }

    private function backfill(TransactionDiscoveryService $svc, int $hours): int
    {
        $stores = \App\Models\LaundrycardStore::where('status', 'active')
            ->when($this->option('store'), fn ($q, $s) => $q->where('store_id', \App\Support\Normalizer::storeId($s)))
            ->orderBy('store_id')->get();

        $rows = [];
        foreach ($stores as $store) {
            $this->info("Backfilling store {$store->store_id} ({$hours}h)...");
            try {
                $r = $svc->backfillStore($store, $hours, fn ($from, $w) => $this->line("  {$from->toDateTimeString()}: +{$w['inserted']} ({$w['raw_count']} rows)"));
                $rows[] = [$store->store_id, $r['inserted'] ?? '-', $r['duplicate'] ?? '-', $r['raw_count'] ?? '-', $r['windows'] ?? ($r['skipped'] ?? '-')];
            } catch (\Throwable $e) {
                $rows[] = [$store->store_id, 'ERROR', '-', '-', \App\Support\Sanitizer::string($e->getMessage(), 80)];
            }
        }
        $this->table(['Store', 'Inserted', 'Duplicates', 'Raw rows', 'Windows'], $rows);

        return self::SUCCESS;
    }

    private function dryRun(): int
    {
        $this->line('--- Transaction Ingestion Dry-Run ---');
        $this->line('Enabled: ' . (config('integration.transactions.enabled') ? 'YES' : 'NO (set INTEGRATION_TRANSACTION_INGESTION_ENABLED=true)'));
        $this->line('Window:  ' . config('integration.transactions.window_hours') . 'h');
        $this->line('Overlap: ' . config('integration.transactions.overlap_minutes') . 'min');
        $this->newLine();

        $stores = \App\Models\LaundrycardStore::where('status', 'active')->orderBy('store_id')->get();
        foreach ($stores as $store) {
            $cursor = $store->last_transaction_cursor_at
                ? $store->last_transaction_cursor_at->toDateTimeString()
                : 'none (will use ' . config('integration.transactions.window_hours') . 'h lookback)';
            $txCount = \App\Models\LaundrycardTransaction::where('laundrycard_store_id', $store->id)->count();
            $this->line("Store {$store->store_id}: cursor={$cursor} | stored_tx={$txCount}");
        }

        $totalTx = \App\Models\LaundrycardTransaction::count();
        $this->newLine();
        $this->line("Total transactions stored: {$totalTx}");

        return self::SUCCESS;
    }
}

