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
        // Recover any runs left in 'running' state by a previous invocation that was killed
        // mid-flight (e.g. Vercel function timeout). The cache lock TTL is 900s, so any run
        // still marked running after 20 minutes has definitively orphaned.
        SyncRun::where('status', 'running')
            ->where('started_at', '<', now()->subMinutes(20))
            ->each(function (SyncRun $orphan): void {
                $orphan->update([
                    'status'      => 'failed',
                    'finished_at' => $orphan->started_at->addMinutes(20),
                    'error'       => 'Run timed out or was killed before it could finish (recovered on next invocation).',
                ]);
                $this->logger->log('discovery.run', [
                    'outcome' => 'failed',
                    'message' => "Orphaned run #{$orphan->id} recovered and marked failed.",
                    'context' => ['correlation_id' => $orphan->correlation_id],
                ]);
            });

        $correlation = $this->logger->newCorrelation();
        $run = SyncRun::create([
            'correlation_id' => $correlation, 'type' => 'discovery', 'mode' => $readOnly ? 'read_only' : 'live',
            'status' => 'running', 'trigger' => $trigger, 'user_id' => $userId, 'started_at' => now(),
        ]);

        $lock = Cache::lock('integration:discovery', 300);
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
                if ($store->live_enabled === false && $store->baseline_completed_at !== null && ! $onlyStoreId) {
                    $stats['stores'][$store->store_id] = [
                        'skipped' => 'store_live_disabled',
                        'message' => 'Store LiveEnabled=false; on-premise X-Changer VPN is offline at CCI Live.',
                    ];
                    if (! $readOnly) {
                        $store->update([
                            'last_scan_at'     => now(),
                            'last_scan_status' => 'skipped_offline',
                            'last_scan_error'  => 'Store LiveEnabled=false; on-premise X-Changer VPN is offline at CCI Live.',
                        ]);
                    }
                    $this->logger->log('discovery.store', [
                        'store_id' => $store->store_id,
                        'outcome'  => 'skipped',
                        'message'  => 'Skipped card poll because store LiveEnabled=false (on-premise X-Changer VPN offline).',
                    ]);

                    continue;
                }

                try {
                    $res = $this->scanStore($store, $readOnly);
                    $stats['stores_scanned']++;
                    foreach (['cards_seen', 'new_records', 'baseline_records', 'held'] as $k) {
                        $stats[$k] += $res[$k];
                    }
                    $stats['stores'][$store->store_id] = $res;
                } catch (Throwable $e) {
                    $stats['stores_failed']++;
                    $errMsg = $e->getMessage();
                    if ($store->live_enabled === false && str_contains($errMsg, 'HTTP 500')) {
                        $errMsg .= ' (Store LiveEnabled=false; on-premise X-Changer VPN is offline at CCI Live)';
                    }
                    $stats['stores'][$store->store_id] = ['error' => class_basename($e), 'message' => \App\Support\Sanitizer::string($errMsg, 200)];
                    if (! $readOnly) {
                        $store->update(['last_scan_at' => now(), 'last_scan_status' => 'failed', 'last_scan_error' => \App\Support\Sanitizer::string($errMsg, 300)]);
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
        $hasExistingRecords = Rec::where('laundrycard_store_id', $store->id)->exists();
        $isBaseline = $store->baseline_completed_at === null && ! $hasExistingRecords;

        if ($isBaseline) {
            return $this->scanStoreBaseline($store, $readOnly);
        }

        // Stores that already have records have effectively completed baseline initialization.
        if ($store->baseline_completed_at === null && ! $readOnly) {
            $store->update(['baseline_completed_at' => now()]);
        }

        return $this->scanStoreIncremental($store, $readOnly);
    }

    /**
     * High-performance incremental discovery using LaundryCard's MinDateTime filter
     * with an overlap safety window to guarantee late-arriving records are never missed.
     */
    public function scanStoreIncremental(LaundrycardStore $store, bool $readOnly): array
    {
        $maxLookbackHours = (int) config('integration.discovery.max_lookback_hours', 2);
        $cursor = $store->last_sync_cursor_at;
        if (! $cursor) {
            $cursor = ($store->last_successful_scan_at && $store->last_successful_scan_at->gt(now()->subHours($maxLookbackHours)))
                ? $store->last_successful_scan_at
                : now()->subHours($maxLookbackHours);
        }

        $overlapMinutes = (int) config('integration.discovery.overlap_minutes', 30);
        $since = \Carbon\CarbonImmutable::parse($cursor)->subMinutes($overlapMinutes)->toIso8601ZuluString();

        $cards = $this->client->cards($store->store_id, minDateTime: $since);
        $res = [
            'mode' => 'incremental',
            'cards_seen' => count($cards),
            'new_records' => 0,
            'baseline_records' => 0,
            'updated_records' => 0,
            'held' => 0,
            'cursor_used' => $since,
        ];

        if (empty($cards)) {
            if (! $readOnly) {
                $store->update([
                    'last_scan_at' => now(),
                    'last_successful_scan_at' => now(),
                    'last_scan_status' => 'ok',
                    'last_scan_error' => null,
                    'last_sync_cursor_at' => now(),
                ]);
            }

            return $res;
        }

        $cardNumbers = array_column($cards, 'card_number');
        $existing = Rec::where('laundrycard_store_id', $store->id)
            ->whereIn('card_number', $cardNumbers)
            ->get()
            ->keyBy('card_number');

        $maxObservedTs = null;
        $newRecordsToInsert = [];

        foreach ($cards as $card) {
            if ($card['source_timestamp']) {
                if ($maxObservedTs === null || $card['source_timestamp']->gt($maxObservedTs)) {
                    $maxObservedTs = $card['source_timestamp'];
                }
            }

            if ($existing->has($card['card_number'])) {
                if (! $readOnly) {
                    $record = $existing->get($card['card_number']);
                    $this->observe($store, $record, $card, isNew: false);
                    $res['updated_records']++;
                    if ($record->processing_status === Rec::HELD) {
                        $res['held']++;
                    }
                }

                continue;
            }

            // Brand new customer record discovered!
            $res['new_records']++;

            if ($readOnly) {
                continue;
            }

            $record = Rec::create([
                'laundrycard_store_id' => $store->id,
                'card_number' => $card['card_number'],
                'source_timestamp' => $card['source_timestamp'],
                'first_observed_at' => now(),
                'last_observed_at' => now(),
                'classification' => Rec::NEW_OBSERVED,
                'processing_status' => Rec::HELD,
                'hold_reason' => 'balance_not_verified',
                'eligibility_status' => Rec::BALANCE_UNKNOWN,
                'eligibility_reason' => 'balance_not_verified',
            ]);

            $this->observe($store, $record, $card, isNew: true);
            if ($record->processing_status === Rec::HELD) {
                $res['held']++;
            }
        }

        if (! $readOnly) {
            $nextCursor = $maxObservedTs ? $maxObservedTs->toDateTimeString() : now();
            $store->update([
                'last_scan_at' => now(),
                'last_successful_scan_at' => now(),
                'last_scan_status' => 'ok',
                'last_scan_error' => null,
                'last_sync_cursor_at' => $nextCursor,
            ]);
        }

        return $res;
    }

    private function evaluateCardBalance(array $card, ?string $previousBalance = null, ?string $previousEligibility = null): array
    {
        $balance = $card['balance'] ?? null;
        $present = (bool) ($card['balance_present'] ?? false);

        if (! $present) {
            return ['eligibility' => Rec::BALANCE_UNKNOWN, 'reason' => 'balance_missing', 'balance' => $balance];
        }
        if ($balance === null) {
            return ['eligibility' => Rec::BALANCE_INVALID, 'reason' => 'balance_invalid', 'balance' => $balance];
        }
        if ((float) $balance > 0) {
            $reason = $previousBalance !== null && (float) $previousBalance <= 0
                ? 'balance_became_positive'
                : 'positive_balance';

            return ['eligibility' => Rec::BALANCE_ELIGIBLE, 'reason' => $reason, 'balance' => $balance];
        }
        if ((float) $balance === 0.0) {
            return ['eligibility' => Rec::BALANCE_ZERO, 'reason' => 'balance_not_positive', 'balance' => $balance];
        }

        return ['eligibility' => Rec::BALANCE_NEGATIVE, 'reason' => 'balance_not_positive', 'balance' => $balance];
    }

    /**
     * Initial store baseline scan with pagination support using MinCardNum.
     */
    public function scanStoreBaseline(LaundrycardStore $store, bool $readOnly): array
    {
        $cursor = $store->last_sync_card_cursor;
        $cards = $this->client->cards($store->store_id, minCardNum: $cursor);
        $res = [
            'mode' => 'baseline',
            'cards_seen' => count($cards),
            'new_records' => 0,
            'baseline_records' => 0,
            'updated_records' => 0,
            'held' => 0,
        ];

        if (! $readOnly && $store->baseline_started_at === null) {
            $store->update(['baseline_started_at' => now()]);
        }

        if (empty($cards)) {
            if (! $readOnly) {
                $store->update([
                    'baseline_completed_at' => now(),
                    'last_scan_at' => now(),
                    'last_successful_scan_at' => now(),
                    'last_scan_status' => 'ok',
                    'last_scan_error' => null,
                    'last_sync_cursor_at' => now(),
                    'last_sync_card_cursor' => null,
                ]);
            }

            return $res;
        }

        $cardNumbers = array_column($cards, 'card_number');
        $existing = Rec::where('laundrycard_store_id', $store->id)
            ->whereIn('card_number', $cardNumbers)
            ->pluck('id', 'card_number');

        $lastCardNum = null;
        foreach ($cards as $card) {
            $lastCardNum = $card['card_number'];
            if ($existing->has($card['card_number'])) {
                continue;
            }

            $classification = Rec::BASELINE;
            $res['new_records']++;
            $res['baseline_records']++;

            if ($readOnly) {
                continue;
            }

            $initialStatus = config('integration.discovery.initial_import') === 'import' ? Rec::HELD : Rec::BASELINE_SKIPPED;
            $initialHoldReason = config('integration.discovery.initial_import') === 'import' ? 'balance_not_verified' : 'initial_import_disabled';

            $record = Rec::create([
                'laundrycard_store_id' => $store->id,
                'card_number' => $card['card_number'],
                'source_timestamp' => $card['source_timestamp'],
                'first_observed_at' => now(),
                'last_observed_at' => now(),
                'classification' => $classification,
                'processing_status' => $initialStatus,
                'hold_reason' => $initialHoldReason,
                'eligibility_status' => Rec::BALANCE_UNKNOWN,
                'eligibility_reason' => 'balance_not_verified',
            ]);

            $this->observe($store, $record, $card, isNew: true, deferInitialBaseline: config('integration.discovery.initial_import') !== 'import');
            if ($record->processing_status === Rec::HELD) {
                $res['held']++;
            }
        }

        if (! $readOnly) {
            $maxBatch = (int) config('integration.discovery.max_baseline_batch', 10000);
            $hasMore = count($cards) >= $maxBatch;
            $store->update([
                'last_scan_at' => now(),
                'last_successful_scan_at' => now(),
                'last_scan_status' => 'ok',
                'last_scan_error' => null,
                'last_sync_card_cursor' => $hasMore ? $lastCardNum : null,
                'baseline_completed_at' => $hasMore ? null : ($store->baseline_completed_at ?? now()),
                'last_sync_cursor_at' => $hasMore ? null : now(),
            ]);
        }

        return $res;
    }

    /** Persist every balance observation and release zero-balance records when they turn positive. */
    private function observe(LaundrycardStore $store, Rec $record, array $card, bool $isNew = false, bool $deferInitialBaseline = false): void
    {
        $balance = $card['balance'] ?? null;
        $present = (bool) ($card['balance_present'] ?? false);
        $customer = [
            'name'  => $card['name'] ?? null,
            'email' => $card['email'] ?? null,
            'phone' => $card['phone'] ?? null,
        ];

        if (
            $isNew
            && (! $present || $balance === null || (! $customer['email'] && ! $customer['phone']))
            && config('integration.discovery.enrich_with_account_detail')
        ) {
            try {
                $account = $this->client->account($store->store_id, $record->card_number);
                if ($account) {
                    $customer = [
                        'name'  => $account['name'] ?: $customer['name'],
                        'email' => $account['email'] ?: $customer['email'],
                        'phone' => $account['phone'] ?: $customer['phone'],
                    ];
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

        $balanceChanged = (string) $previousBalance !== (string) $balance;
        $statusChanged = $previousEligibility !== $eligibility;
        $tsChanged = false;
        if ($card['source_timestamp']) {
            $tsChanged = ! $record->source_timestamp || $card['source_timestamp']->timestamp !== $record->source_timestamp->timestamp;
        }

        if (! $isNew && ! $balanceChanged && ! $statusChanged && ! $tsChanged) {
            return;
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
            // A zero or negative card that transitions to positive balance qualifies for release.
            $becamePositive = ! $wasEligible && $previousEligibility !== null && $previousEligibility !== Rec::BALANCE_UNKNOWN;

            if ($becamePositive) {
                $attrs['processing_status'] = Rec::PENDING;
                $attrs['hold_reason'] = null;
            } elseif ($deferInitialBaseline || (
                $record->classification === Rec::BASELINE
                && config('integration.discovery.initial_import') !== 'import'
                && $record->attempt_count === 0
            )) {
                $attrs['processing_status'] = Rec::BASELINE_SKIPPED;
                $attrs['hold_reason'] = 'initial_import_disabled';
            } else {
                $attrs['processing_status'] = Rec::PENDING;
                $attrs['hold_reason'] = null;
            }
        } elseif ($eligibility === Rec::BALANCE_ELIGIBLE && $record->isSynced() && $balanceChanged) {
            $attrs['processing_status'] = Rec::PENDING;
            $attrs['hold_reason'] = null;
        } elseif ($eligibility !== Rec::BALANCE_ELIGIBLE && ! $record->isSynced()) {
            if ($record->classification === Rec::BASELINE && config('integration.discovery.initial_import') !== 'import') {
                $attrs['processing_status'] = Rec::BASELINE_SKIPPED;
                $attrs['hold_reason'] = 'initial_import_disabled';
            } else {
                $attrs['processing_status'] = Rec::HELD;
                $attrs['hold_reason'] = $reason;
            }
        }

        $record->update($attrs);
        if (! $isNew && ((string) $previousBalance !== (string) $balance || $previousEligibility !== $eligibility)) {
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
