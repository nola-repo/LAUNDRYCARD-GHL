<?php

namespace App\Services;

use App\Exceptions\Integration\IntegrationException;
use App\Exceptions\Integration\RetryableException;
use App\Models\LaundrycardSourceRecord as Rec;
use App\Models\LaundrycardStore;
use App\Support\Normalizer;
use Illuminate\Support\Facades\Cache;

/**
 * POS Account enrichment for existing anonymous card records.
 *
 * "Anonymous" means: no customer_name AND no customer_email AND no customer_phone.
 * We only enrich cards where a MachNo is configured for the store.
 * We never re-enrich cards that already have all three identity fields.
 * We never stop the whole batch because one card fails.
 *
 * Rate-limit cooldowns are stored per-store in the cache.
 * Enrichment skips a store that is currently on cooldown.
 */
class EnrichmentService
{
    /** @var int Maximum cards to enrich per store per run (prevents Vercel timeouts). */
    private const BATCH_SIZE = 50;

    /** @var int Cooldown in seconds after a 429 from the POS Account endpoint. */
    private const RATE_LIMIT_COOLDOWN_TTL = 120;

    /** @var string Cache key prefix for per-store rate-limit cooldowns. */
    private const RATE_LIMIT_KEY = 'laundrycard:pos_mach_rate_limit:';

    public function __construct(
        private LaundryCardClient $client,
        private IntegrationLogger $logger,
    ) {}

    /**
     * Enrich anonymous records for all stores (or a specific store).
     * Returns a summary keyed by store_id.
     *
     * @return array<string, array{enriched:int, skipped:int, failed:int, no_mach:bool}>
     */
    public function run(?string $onlyStoreId = null): array
    {
        if (! config('integration.discovery.enrich_with_account_detail')) {
            return [];
        }

        $stores = LaundrycardStore::where('status', 'active')
            ->when($onlyStoreId, fn ($q) => $q->where('store_id', Normalizer::storeId($onlyStoreId)))
            ->orderBy('store_id')
            ->get();

        $summary = [];

        foreach ($stores as $store) {
            $summary[$store->store_id] = $this->enrichStore($store);
        }

        return $summary;
    }

    /**
     * Enrich anonymous records for a single store.
     *
     * @return array{enriched:int, skipped:int, failed:int, no_mach:bool}
     */
    public function enrichStore(LaundrycardStore $store): array
    {
        $result = ['enriched' => 0, 'skipped' => 0, 'failed' => 0, 'rate_limited' => 0, 'no_mach' => false];

        if (! $this->client->hasMachNo($store->store_id)) {
            $this->logger->log('enrichment.store', [
                'store_id' => $store->store_id,
                'outcome'  => 'skipped',
                'message'  => "No MachNo configured for store {$store->store_id} — POS Account enrichment unavailable.",
                'level'    => 'warning',
            ]);
            $result['no_mach'] = true;

            return $result;
        }

        // Check per-store rate-limit cooldown.
        if ($this->isOnCooldown($store->store_id)) {
            $this->logger->log('enrichment.store', [
                'store_id' => $store->store_id,
                'outcome'  => 'rate_limited',
                'message'  => "Store {$store->store_id} is on POS rate-limit cooldown; skipping enrichment.",
                'level'    => 'warning',
            ]);
            $result['skipped']++;

            return $result;
        }

        // Find anonymous records: missing at least one identity field, not yet synced.
        $records = Rec::where('laundrycard_store_id', $store->id)
            ->where(function ($q) {
                $q->whereNull('customer_name')
                    ->orWhereNull('customer_email')
                    ->orWhereNull('customer_phone');
            })
            ->whereNotIn('processing_status', [Rec::SYNCED_CREATED, Rec::SYNCED_UPDATED])
            ->orderBy('id')
            ->limit(self::BATCH_SIZE)
            ->get();

        foreach ($records as $record) {
            $outcome = $this->enrichRecord($store, $record);
            $result[$outcome]++;

            if ($outcome === 'rate_limited') {
                // Stop the store batch immediately on 429.
                break;
            }
        }

        return $result;
    }

    /**
     * Enrich a single record via the POS Account endpoint.
     * Returns 'enriched', 'skipped', 'failed', or 'rate_limited'.
     */
    public function enrichRecord(LaundrycardStore $store, Rec $record): string
    {
        // Skip if already fully identified.
        if ($this->isFullyIdentified($record)) {
            return 'skipped';
        }

        try {
            $account = $this->client->account($store->store_id, $record->card_number);
        } catch (RetryableException $e) {
            if ($e->httpStatus === 429) {
                $this->setCooldown($store->store_id);
                $this->logger->error('enrichment.ratelimit', $e, [
                    'store_id'        => $store->store_id,
                    'source_record_id' => $record->id,
                ]);

                return 'rate_limited';
            }
            // Transient connectivity error — log and continue to the next record.
            $this->logger->error('enrichment.retry', $e, [
                'store_id'        => $store->store_id,
                'source_record_id' => $record->id,
            ]);

            return 'failed';
        } catch (IntegrationException $e) {
            $this->logger->error('enrichment.error', $e, [
                'store_id'        => $store->store_id,
                'source_record_id' => $record->id,
            ]);

            return 'failed';
        }

        if ($account === null) {
            // 404: card has no POS account — not an error, just unavailable.
            $this->logger->log('enrichment.not_found', [
                'store_id'        => $store->store_id,
                'source_record_id' => $record->id,
                'outcome'         => 'not_found',
                'message'         => "Card {$record->card_number} has no POS Account (404).",
            ]);

            return 'skipped';
        }

        $this->applyAccountData($record, $account);

        $this->logger->log('enrichment.success', [
            'store_id'        => $store->store_id,
            'source_record_id' => $record->id,
            'outcome'         => 'enriched',
            'message'         => "Card {$record->card_number} enriched via POS Account.",
            'context'         => [
                'has_name'  => filled($account['name']),
                'has_email' => filled($account['email']),
                'has_phone' => filled($account['phone']),
            ],
        ]);

        return 'enriched';
    }

    // ------------------------------------------------------------------ internals

    /**
     * Apply account data to a record, preserving any existing verified values.
     * Never overwrites a non-empty field — the existing value is kept.
     */
    private function applyAccountData(Rec $record, array $account): void
    {
        $attrs = [];

        $name  = filled($account['name']) ? $account['name'] : null;
        $email = Normalizer::email($account['email'] ?? null);
        $phone = Normalizer::phone($account['phone'] ?? null);

        // Only set if currently missing.
        if ($name && ! filled($record->customer_name)) {
            $attrs['customer_name'] = $name;
        }
        if ($email && ! filled($record->customer_email)) {
            $attrs['customer_email'] = $email;
            $attrs['email_hash']     = Normalizer::hash($email);
        }
        if ($phone && ! filled($record->customer_phone)) {
            $attrs['customer_phone'] = $phone;
            $attrs['phone_hash']     = Normalizer::hash($phone);
        }

        // Balance from POS Account takes priority over the card endpoint balance
        // only if the card endpoint didn't already give us a valid balance.
        if (($account['balance_present'] ?? false) && $account['balance'] !== null && $record->balance === null) {
            $attrs['balance'] = $account['balance'];
        }

        if (! empty($attrs)) {
            $record->update($attrs);
        }
    }

    private function isFullyIdentified(Rec $record): bool
    {
        return filled($record->customer_name)
            && filled($record->customer_email)
            && filled($record->customer_phone);
    }

    private function isOnCooldown(string $storeId): bool
    {
        return Cache::has(self::RATE_LIMIT_KEY.$storeId);
    }

    private function setCooldown(string $storeId): void
    {
        Cache::put(self::RATE_LIMIT_KEY.$storeId, true, self::RATE_LIMIT_COOLDOWN_TTL);
    }
}

