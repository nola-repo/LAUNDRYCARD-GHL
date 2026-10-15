<?php

namespace App\Services;

use App\Exceptions\Integration\IntegrationException;
use App\Jobs\SyncSourceRecordJob;
use App\Models\LaundrycardSourceRecord as Rec;
use App\Models\LaundrycardStore;
use App\Models\SyncRun;
use App\Support\Normalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Polling discovery.
 *
 * Research result (CCI docs): no customer-created webhook, no incremental/"since" filter,
 * no documented pagination on /api/cards, and the card "Timestamp" is not documented as a
 * creation time. We therefore poll each accessible store, diff against persistent state and
 * Balance eligibility is evaluated on every observation. LaundryCard's card Timestamp is
 * retained for monitoring only; its documentation does not define it as customer creation.
 */
class CustomerDiscoveryService
{
    /** Hold reasons that are released automatically once routing becomes possible. */
    public const RELEASABLE_HOLDS = ['no_mapping', 'mapping_not_verified', 'connection_unusable', 'sync_disabled'];

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
            'baseline_records' => 0, 'held' => 0, 'queued' => 0, 'stores' => []];

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
                    foreach (['cards_seen', 'new_records', 'baseline_records', 'held'] as $k) {
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
        $res = ['cards_seen' => count($cards), 'new_records' => 0, 'baseline_records' => 0, 'held' => 0];

        $isBaseline = $store->baseline_completed_at === null;
        if ($isBaseline && ! $readOnly && $store->baseline_started_at === null) {
            $store->update(['baseline_started_at' => now()]);
        }
        $existing = Rec::where('laundrycard_store_id', $store->id)->pluck('id', 'card_number');

        foreach ($cards as $card) {
            if ($existing->has($card['card_number'])) {
                if (! $readOnly) {
                    $record = Rec::find($existing[$card['card_number']]);
                    if ($record) {
                        $this->observe($store, $record, $card);
                    }
                }

                continue;
            }

            $classification = $isBaseline ? Rec::BASELINE : Rec::NEW_OBSERVED;
            $res['new_records']++;
            if ($classification === Rec::BASELINE) {
                $res['baseline_records']++;
            }
            if ($readOnly) {
                continue;
            }

            $record = Rec::firstOrCreate(
                ['laundrycard_store_id' => $store->id, 'card_number' => $card['card_number']],
                [
                    'source_timestamp' => $card['source_timestamp'], 'first_observed_at' => now(), 'last_observed_at' => now(),
                    'classification' => $classification,
                    'processing_status' => $classification === Rec::BASELINE && config('integration.discovery.initial_import') !== 'import' ? Rec::BASELINE_SKIPPED : Rec::HELD,
                    'hold_reason' => $classification === Rec::BASELINE && config('integration.discovery.initial_import') !== 'import' ? 'initial_import_disabled' : 'balance_not_verified',
                    'eligibility_status' => Rec::BALANCE_UNKNOWN, 'eligibility_reason' => 'balance_not_verified',
                ]
            );
            $this->observe(
                $store,
                $record,
                $card,
                $record->wasRecentlyCreated
                    && $classification === Rec::BASELINE
                    && config('integration.discovery.initial_import') !== 'import'
            );
            if ($record->processing_status === Rec::HELD) {
                $res['held']++;
            }
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

    /** Persist every balance observation and release zero-balance records when they turn positive. */
    private function observe(LaundrycardStore $store, Rec $record, array $card, bool $deferInitialBaseline = false): void
    {
        $balance = $card['balance'] ?? null;
        $present = (bool) ($card['balance_present'] ?? false);
        $customer = ['name' => null, 'email' => $card['email'] ?? null, 'phone' => null];

        if (
            (! $present || $balance === null || (! $customer['email'] && ! $customer['phone']))
            && config('integration.discovery.enrich_with_account_detail')
        ) {
            try {
                $account = $this->client->account($store->store_id, $record->card_number);
                if ($account) {
                    $customer = $account + $customer;
                    if ($account['balance_present'] ?? false) {
                        $balance = $account['balance'];
                        $present = true;
                    }
                }
            } catch (IntegrationException $e) {
                $this->logger->error('discovery.enrich', $e, ['store_id' => $store->store_id]);
            }
        }

        $previousBalance = $record->balance;
        $previousEligibility = $record->eligibility_status;
        if (! $present) {
            $eligibility = Rec::BALANCE_UNKNOWN;
            $reason = 'balance_missing';
        } elseif ($balance === null) {
            $eligibility = Rec::BALANCE_INVALID;
            $reason = 'balance_invalid';
        } elseif ((float) $balance > 0) {
            $eligibility = Rec::BALANCE_ELIGIBLE;
            $reason = $previousBalance !== null && (float) $previousBalance <= 0
                ? 'balance_became_positive'
                : 'positive_balance';
        } elseif ((float) $balance === 0.0) {
            $eligibility = Rec::BALANCE_ZERO;
            $reason = 'balance_not_positive';
        } else {
            $eligibility = Rec::BALANCE_NEGATIVE;
            $reason = 'balance_not_positive';
        }

        $email = Normalizer::email($customer['email'] ?? null);
        $phone = Normalizer::phone($customer['phone'] ?? null);
        $attrs = [
            'last_observed_at' => now(),
            'source_timestamp' => $card['source_timestamp'] ?? $record->source_timestamp,
            'balance' => $balance,
            'eligibility_status' => $eligibility,
            'eligibility_reason' => $reason,
        ];
        if ($customer['name'] ?? null) {
            $attrs['customer_name'] = $customer['name'];
        }
        if ($email) {
            $attrs['customer_email'] = $email;
            $attrs['email_hash'] = Normalizer::hash($email);
        }
        if ($phone) {
            $attrs['customer_phone'] = $phone;
            $attrs['phone_hash'] = Normalizer::hash($phone);
        }

        $wasEligible = $previousEligibility === Rec::BALANCE_ELIGIBLE;
        if ($eligibility === Rec::BALANCE_ELIGIBLE && ! $record->isSynced()) {
            $historicalImportDeferred = $deferInitialBaseline || (
                $record->classification === Rec::BASELINE
                && config('integration.discovery.initial_import') !== 'import'
                && $record->attempt_count === 0
                && $record->processing_status === Rec::BASELINE_SKIPPED
            );
            if ($historicalImportDeferred) {
                $attrs['processing_status'] = Rec::BASELINE_SKIPPED;
                $attrs['hold_reason'] = 'initial_import_disabled';
            } elseif (! $wasEligible || $record->processing_status === Rec::BASELINE_SKIPPED) {
                $attrs['processing_status'] = Rec::PENDING;
                $attrs['hold_reason'] = null;
            }
        } elseif ($eligibility !== Rec::BALANCE_ELIGIBLE && ! $record->isSynced()) {
            $attrs['processing_status'] = Rec::HELD;
            $attrs['hold_reason'] = $reason;
        }

        $record->update($attrs);
        if ((string) $previousBalance !== (string) $balance || $previousEligibility !== $eligibility) {
            $this->logger->log('eligibility.balance', [
                'store_id' => $store->store_id,
                'outcome' => $eligibility,
                'message' => 'Card balance eligibility changed.',
                'context' => ['reason' => $reason, 'processing_status' => $record->processing_status, 'hold_reason' => $record->hold_reason],
            ]);
        }

    }

    /**
     * Queue records whose store is routable; hold the rest with an explicit reason.
     * Safe to call repeatedly (atomic status transition prevents double dispatch).
     */
    public function dispatchPending(?LaundrycardStore $only = null): int
    {
        if (! config('integration.sync.enabled')) {
            return 0;
        }

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
