<?php

namespace App\Services;

use App\Exceptions\Integration\IntegrationException;
use App\Jobs\SyncSourceRecordJob;
use App\Models\LaundrycardSourceRecord as Rec;
use App\Models\LaundrycardStore;
use App\Models\SyncRun;
use App\Support\Normalizer;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Polling discovery.
 *
 * Research result (CCI docs): no customer-created webhook, no incremental/"since" filter,
 * no documented pagination on /api/cards, and the card "Timestamp" is not documented as a
 * creation time. We therefore poll each accessible store, diff against persistent state and
 * only treat a card as a *confirmed* new customer when it appeared after the store baseline
 * AND carries a source timestamp on/after that baseline.
 */
class CustomerDiscoveryService
{
    /** Hold reasons that are released automatically once routing becomes possible. */
    public const RELEASABLE_HOLDS = ['no_mapping', 'mapping_not_verified', 'connection_unusable'];

    public function __construct(
        private LaundryCardClient $client,
        private BranchMappingService $mappings,
        private IntegrationLogger $logger,
    ) {
    }

    /**
     * @param  bool  $readOnly  true => nothing is persisted except the run summary; no jobs dispatched.
     */
    public function run(bool $readOnly = false, string $trigger = 'schedule', ?int $userId = null, ?string $onlyStoreId = null): SyncRun
    {
        $correlation = $this->logger->newCorrelation();
        $run = SyncRun::create([
            'correlation_id' => $correlation, 'type' => 'discovery', 'mode' => $readOnly ? 'read_only' : 'live',
            'status' => 'running', 'trigger' => $trigger, 'user_id' => $userId, 'started_at' => now(),
        ]);

        $lock = Cache::lock('integration:discovery', 900);
        if (! $lock->get()) {
            $run->update(['status' => 'failed', 'finished_at' => now(), 'error' => 'Another discovery run is in progress.']);

            return $run;
        }

        $stats = ['stores_scanned' => 0, 'stores_failed' => 0, 'cards_seen' => 0, 'new_records' => 0,
            'baseline_records' => 0, 'confirmed_new' => 0, 'held' => 0, 'queued' => 0, 'stores' => []];

        try {
            if (! $readOnly) {
                $this->mappings->refreshStores($this->client);
            }

            $stores = LaundrycardStore::where('status', 'active')
                ->when($onlyStoreId, fn ($q) => $q->where('store_id', Normalizer::storeId($onlyStoreId)))->orderBy('store_id')->get();

            foreach ($stores as $store) {
                try {
                    $res = $this->scanStore($store, $readOnly);
                    $stats['stores_scanned']++;
                    foreach (['cards_seen', 'new_records', 'baseline_records', 'confirmed_new', 'held'] as $k) {
                        $stats[$k] += $res[$k];
                    }
                    $stats['stores'][$store->store_id] = $res;
                } catch (Throwable $e) {
                    $stats['stores_failed']++;
                    $stats['stores'][$store->store_id] = ['error' => class_basename($e)];
                    if (! $readOnly) {
                        $store->update(['last_scan_at' => now(), 'last_scan_status' => 'failed', 'last_scan_error' => \App\Support\Sanitizer::string($e->getMessage(), 300)]);
                    }
                    $this->logger->error('discovery.store', $e, ['store_id' => $store->store_id]);
                    // Progress is NOT advanced for this store; the next run rescans it fully.
                }
            }

            if (! $readOnly) {
                $stats['queued'] = $this->dispatchPending();
            }

            $status = $stats['stores_failed'] === 0 ? 'completed' : ($stats['stores_scanned'] > 0 ? 'partial' : 'failed');
            $run->update(['status' => $status, 'finished_at' => now(), 'stats' => $stats]);
        } catch (Throwable $e) {
            $run->update(['status' => 'failed', 'finished_at' => now(), 'stats' => $stats, 'error' => \App\Support\Sanitizer::string($e->getMessage(), 500)]);
            $this->logger->error('discovery.run', $e);
        } finally {
            $lock->release();
        }

        $this->logger->log('discovery.run', ['outcome' => $run->status, 'message' => ($readOnly ? 'read-only ' : '').'discovery finished', 'context' => ['stats' => collect($stats)->except('stores')->all()]]);

        return $run;
    }

    public function scanStore(LaundrycardStore $store, bool $readOnly): array
    {
        $cards = $this->client->cards($store->store_id);
        $res = ['cards_seen' => count($cards), 'new_records' => 0, 'baseline_records' => 0, 'confirmed_new' => 0, 'held' => 0];

        $isBaseline = $store->baseline_completed_at === null;
        if ($isBaseline && ! $readOnly && $store->baseline_started_at === null) {
            $store->update(['baseline_started_at' => now()]);
        }
        $threshold = ($store->baseline_started_at ?? now())->copy()->subMinutes((int) config('integration.discovery.timestamp_grace_minutes'));
        $importBaseline = config('integration.discovery.initial_import') === 'import';

        $existing = Rec::where('laundrycard_store_id', $store->id)->pluck('id', 'card_number');

        foreach ($cards as $card) {
            if ($existing->has($card['card_number'])) {
                if (! $readOnly) {
                    Rec::where('id', $existing[$card['card_number']])->update([
                        'last_observed_at' => now(), 'source_timestamp' => $card['source_timestamp'],
                    ]);
                }

                continue;
            }

            [$classification, $status, $hold] = $this->classify($isBaseline, $importBaseline, $card['source_timestamp'], $threshold);
            $res['new_records']++;
            if ($classification === Rec::BASELINE) {
                $res['baseline_records']++;
            } elseif ($classification === Rec::CONFIRMED_NEW) {
                $res['confirmed_new']++;
            }
            if ($status === Rec::HELD) {
                $res['held']++;
            }
            if ($readOnly) {
                continue;
            }

            $customer = ['name' => null, 'email' => $card['email'], 'phone' => null];
            // Only enrich genuinely new, importable records (never the historical baseline).
            if ($status === Rec::PENDING && ! $isBaseline && config('integration.discovery.enrich_with_account_detail')) {
                $customer = $this->enrich($store, $card, $customer);
            }
            $email = Normalizer::email($customer['email']);
            $phone = Normalizer::phone($customer['phone']);
            if ($status === Rec::PENDING && ! $email && ! $phone) {
                [$status, $hold] = [Rec::SKIPPED, 'no_contact_identifier'];
            }

            Rec::firstOrCreate(
                ['laundrycard_store_id' => $store->id, 'card_number' => $card['card_number']],
                [
                    'source_timestamp' => $card['source_timestamp'], 'first_observed_at' => now(), 'last_observed_at' => now(),
                    'classification' => $classification, 'processing_status' => $status, 'hold_reason' => $hold,
                    'customer_name' => $customer['name'], 'customer_email' => $email, 'customer_phone' => $phone,
                    'email_hash' => Normalizer::hash($email), 'phone_hash' => Normalizer::hash($phone),
                ]
            );
        }

        if (! $readOnly) {
            $store->update([
                'last_scan_at' => now(), 'last_successful_scan_at' => now(), 'last_scan_status' => 'ok', 'last_scan_error' => null,
                // The baseline is only complete once a full scan finished without errors.
                'baseline_completed_at' => $store->baseline_completed_at ?? now(),
            ]);
        }

        return $res;
    }

    /** @return array{0:string,1:string,2:?string} classification, status, hold reason */
    private function classify(bool $isBaseline, bool $importBaseline, ?CarbonInterface $ts, CarbonInterface $threshold): array
    {
        if ($isBaseline) {
            return [Rec::BASELINE, $importBaseline ? Rec::PENDING : Rec::BASELINE_SKIPPED, null];
        }
        if ($ts && $ts->greaterThanOrEqualTo($threshold)) {
            return [Rec::CONFIRMED_NEW, Rec::PENDING, null];
        }
        // New to us but no proof it is a new customer (card could pre-date us, or the API omits timestamps).
        if (config('integration.discovery.require_timestamp_confirmation')) {
            return [Rec::NEW_OBSERVED, Rec::HELD, 'unconfirmed_new_customer'];
        }

        return [Rec::NEW_OBSERVED, Rec::PENDING, null];
    }

    private function enrich(LaundrycardStore $store, array $card, array $customer): array
    {
        try {
            $acct = $this->client->account($store->store_id, $card['card_number']);
        } catch (IntegrationException $e) {
            $this->logger->error('discovery.enrich', $e, ['store_id' => $store->store_id]);

            return $customer;
        }
        if (! $acct) {
            return $customer;
        }

        return [
            'name' => $acct['name'] ?: $customer['name'],
            'email' => $acct['email'] ?: $customer['email'],
            'phone' => $acct['phone'] ?: $customer['phone'],
        ];
    }

    /**
     * Queue records whose store is routable; hold the rest with an explicit reason.
     * Safe to call repeatedly (atomic status transition prevents double dispatch).
     */
    public function dispatchPending(?LaundrycardStore $only = null): int
    {
        $queued = 0;
        $stores = LaundrycardStore::query()->when($only, fn ($q) => $q->whereKey($only->id))->get();

        foreach ($stores as $store) {
            $decision = $this->mappings->resolve($store);
            $base = Rec::where('laundrycard_store_id', $store->id);

            if (! $decision['routable']) {
                // Never fall back to another location: just park with the reason.
                (clone $base)->where('processing_status', Rec::PENDING)->update(['processing_status' => Rec::HELD, 'hold_reason' => $decision['reason']]);

                continue;
            }

            $ids = (clone $base)->where(function ($q) {
                $q->where('processing_status', Rec::PENDING)
                    ->orWhere(fn ($q2) => $q2->where('processing_status', Rec::HELD)->whereIn('hold_reason', self::RELEASABLE_HOLDS));
            })->limit((int) config('integration.sync.dispatch_batch'))->pluck('id');

            foreach ($ids as $id) {
                $claimed = Rec::whereKey($id)->whereIn('processing_status', [Rec::PENDING, Rec::HELD])
                    ->update(['processing_status' => Rec::QUEUED, 'hold_reason' => null]);
                if ($claimed) {
                    SyncSourceRecordJob::dispatch($id, (string) Str::uuid());
                    $queued++;
                }
            }
        }

        return $queued;
    }
}
