<?php

namespace App\Jobs;

use App\Exceptions\Integration\RetryableException;
use App\Models\LaundrycardStore;
use App\Services\TransactionDiscoveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued transaction ingestion for one LaundryCard store.
 *
 * Unique per store for 10 minutes to prevent overlapping runs.
 * Retries on transient failures with the same backoff as contact sync.
 */
class IngestTransactionsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 600; // 10-minute uniqueness window

    public function __construct(
        public int $storeId,
        public string $correlationId,
        public string $trigger = 'queue',
    ) {}

    public function uniqueId(): string
    {
        return 'ingest-transactions-store-'.$this->storeId;
    }

    public function tries(): int
    {
        return 3;
    }

    public function handle(TransactionDiscoveryService $svc): void
    {
        $store = LaundrycardStore::find($this->storeId);
        if (! $store) {
            return;
        }

        try {
            $svc->ingestStore($store, $this->correlationId);
        } catch (RetryableException $e) {
            if ($this->attempts() >= $this->tries()) {
                return;
            }
            $delay = $e->retryAfter ?? [60, 180, 600][$this->attempts() - 1] ?? 60;
            $this->release($delay);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('IngestTransactionsJob failed', [
            'store_id'      => $this->storeId,
            'correlation_id' => $this->correlationId,
            'error'         => $e->getMessage(),
        ]);
    }
}

