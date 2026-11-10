<?php

namespace App\Services;

use App\Exceptions\Integration\IntegrationException;
use App\Exceptions\Integration\RetryableException;
use App\Models\LaundrycardSourceRecord as Rec;
use App\Models\LaundrycardStore;
use App\Models\LaundrycardTransaction as Tx;
use App\Models\SyncRun;
use App\Support\Normalizer;
use App\Support\Sanitizer;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Incremental transaction ingestion from GET /api/transact.
 *
 * IMPORTANT: The actual transaction field names and type codes from the
 * LaundryCard API have NOT yet been validated against a live response.
 * This service stores every transaction with its raw_payload preserved,
 * and applies normalization only once field names are confirmed.
 *
 * A transaction cursor per store (last_transaction_cursor_at) is advanced
 * only when a full time window succeeds. Partial windows leave the cursor
 * unchanged so the window is retried on the next run.
 *
 * API endpoint: GET /api/transact?StoreID=&MinDateTime=&MaxDateTime=
 */
class TransactionDiscoveryService
{
    private const LOCK_KEY     = 'integration:transaction_ingest:';
    private const LOCK_TTL     = 300; // 5 min max per store

    // Candidate field names tried in order (case-insensitive via pick()).
    // Updated with verified field names from live API inspection:
    // ID, DateTime, TransType, TransAmount, CardNumber, MachNo.
    private const FIELD_CANDIDATES = [
        'id'            => ['ID', 'Id', 'id'],
        'card_number'   => ['CardNumber', 'Card', 'CardNo'],
        'occurred_at'   => ['DateTime', 'Timestamp', 'TransactionDate', 'Date'],
        'type_raw'      => ['TransType', 'TransactionType', 'Type', 'TxType'],
        'amount'        => ['TransAmount', 'Amount', 'Value', 'TxAmount'],
        'bonus_amount'  => ['BonusAmount', 'Bonus'],
        'balance_after' => ['Balance', 'BalanceAfter', 'NewBalance'],
        'mach_no'       => ['MachNo', 'Machine', 'MachineNo'],
    ];

    // Known type codes → normalised labels.
    // Derived from live /api/transact data (store 1153, 72h sample):
    //   2   cash reload at kiosk (MachNo 1001/1002, whole-dollar amounts)
    //   3   credit-card reload at kiosk (AdditionalInfo = card brand/last4)
    //   14  bonus paired 1:1 with every type-3 reload (~5% of the reload amount)
    //   100 machine vend (washer/dryer MachNo, small fractional amounts)
    // Any other code (1, 6, 9, 12, 30, 32, 101, 701, 702 ...) is stored as
    // 'unknown' with its raw code preserved until its meaning is confirmed.
    private const TYPE_MAP = [
        '2'   => Tx::TYPE_RELOAD,
        '3'   => Tx::TYPE_RELOAD,
        '14'  => Tx::TYPE_BONUS,
        '100' => Tx::TYPE_VEND,
    ];

    /** Rows per bulk insert. */
    private const INSERT_CHUNK = 500;

    /** Window size (hours) used when backfilling history. */
    private const BACKFILL_WINDOW_HOURS = 12;

    /** @var array<string,int|null> card_number => source_record_id cache for one store run */
    private array $recordIdCache = [];

    public function __construct(
        private LaundryCardClient $client,
        private IntegrationLogger $logger,
    ) {}

    /**
     * Run incremental transaction ingestion for all (or one) active stores.
     *
     * @return array{run_id:int,stores:array<string,array>}
     */
    public function run(?string $onlyStoreId = null, string $trigger = 'schedule'): array
    {
        if (! config('integration.transactions.enabled')) {
            return ['run_id' => 0, 'stores' => [], 'skipped' => 'transaction_ingestion_disabled'];
        }

        $correlation = $this->logger->newCorrelation();
        $run = SyncRun::create([
            'correlation_id' => $correlation,
            'type'           => 'transaction_ingest',
            'mode'           => 'live',
            'status'         => 'running',
            'trigger'        => $trigger,
            'started_at'     => now(),
        ]);

        $stores = LaundrycardStore::where('status', 'active')
            ->when($onlyStoreId, fn ($q) => $q->where('store_id', Normalizer::storeId($onlyStoreId)))
            ->orderBy('store_id')
            ->get();

        $storeResults = [];
        $storesFailed = 0;

        foreach ($stores as $store) {
            try {
                $storeResults[$store->store_id] = $this->ingestStore($store, $correlation);
            } catch (Throwable $e) {
                $storesFailed++;
                $storeResults[$store->store_id] = ['error' => class_basename($e), 'message' => Sanitizer::string($e->getMessage(), 200)];
                $this->logger->error('transactions.store', $e, ['store_id' => $store->store_id]);
            }
        }

        $status = $storesFailed === 0 ? 'completed' : (count($storeResults) > $storesFailed ? 'partial' : 'failed');
        $run->update([
            'status'     => $status,
            'finished_at' => now(),
            'stats'      => ['stores' => $storeResults, 'stores_failed' => $storesFailed],
        ]);

        return ['run_id' => $run->id, 'stores' => $storeResults];
    }

    /**
     * Ingest one store's transactions for the current incremental window.
     *
     * @return array{inserted:int,duplicate:int,window_start:string,window_end:string,cursor_advanced:bool,fields_confirmed:bool}
     */
    public function ingestStore(LaundrycardStore $store, string $correlation = ''): array
    {
        $lock = Cache::lock(self::LOCK_KEY.$store->store_id, self::LOCK_TTL);
        if (! $lock->get()) {
            return ['skipped' => 'lock_held', 'store_id' => $store->store_id];
        }

        try {
            return $this->doIngestStore($store, $correlation);
        } finally {
            $lock->release();
        }
    }

    private function doIngestStore(LaundrycardStore $store, string $correlation): array
    {
        $windowHours   = max(1, (int) config('integration.transactions.window_hours', 1));
        $overlapMin    = max(0, (int) config('integration.transactions.overlap_minutes', 10));
        $maxLookback   = max(1, (int) config('integration.transactions.max_lookback_hours', 48));

        // Compute window start from cursor (with overlap), bounded by max lookback.
        $cursor = $store->last_transaction_cursor_at
            ? CarbonImmutable::parse($store->last_transaction_cursor_at)
            : now()->subHours($windowHours);

        $windowStart = $cursor->subMinutes($overlapMin);
        $earliest    = now()->subHours($maxLookback);
        if ($windowStart->lt($earliest)) {
            $windowStart = $earliest;
        }
        $windowEnd = now();

        // LaundryCard timestamps are UTC; always query in UTC regardless of app timezone.
        $minDt = CarbonImmutable::parse($windowStart)->utc()->format('Y-m-d\TH:i:s');
        $maxDt = CarbonImmutable::parse($windowEnd)->utc()->format('Y-m-d\TH:i:s');

        $this->logger->log('transactions.window', [
            'store_id' => $store->store_id,
            'outcome'  => 'started',
            'message'  => "Transaction window: {$minDt} → {$maxDt}",
            'context'  => ['window_hours' => $windowHours, 'overlap_min' => $overlapMin],
        ]);

        // Fetch — may throw RetryableException or IntegrationException.
        $win = $this->ingestWindow($store, $minDt, $maxDt);
        $raw = array_fill(0, $win['raw_count'], null);
        $inserted = $win['inserted'];
        $duplicates = $win['duplicate'];
        $fieldsConfirmed = $win['fields_confirmed'];

        // Advance cursor only on full success.
        $cursorAdvanced = false;
        $store->update(['last_transaction_cursor_at' => $windowEnd->toDateTimeString()]);
        $cursorAdvanced = true;

        // Attempt to link unlinked transactions to source records.
        $this->linkToSourceRecords($store);

        $this->logger->log('transactions.store', [
            'store_id' => $store->store_id,
            'outcome'  => 'completed',
            'message'  => "Transaction ingest done: {$inserted} inserted, {$duplicates} duplicates.",
            'context'  => [
                'inserted'        => $inserted,
                'duplicates'      => $duplicates,
                'fields_confirmed' => $fieldsConfirmed,
                'window_start'    => $minDt,
                'window_end'      => $maxDt,
            ],
        ]);

        return [
            'inserted'         => $inserted,
            'duplicate'        => $duplicates,
            'window_start'     => $minDt,
            'window_end'       => $maxDt,
            'cursor_advanced'  => $cursorAdvanced,
            'fields_confirmed' => $fieldsConfirmed,
            'raw_count'        => count($raw),
        ];
    }

    /**
     * Fetch one time window and bulk-insert it (idempotent via transaction_ref).
     *
     * @return array{inserted:int,duplicate:int,raw_count:int,fields_confirmed:bool}
     */
    private function ingestWindow(LaundrycardStore $store, string $minDt, string $maxDt): array
    {
        $raw = $this->client->transactions($store->store_id, $minDt, $maxDt);
        $this->recordIdCache = [];

        $inserted = 0;
        $duplicates = 0;
        $fieldsConfirmed = false;
        $batch = [];

        $flush = function () use (&$batch, &$inserted, &$duplicates, $store) {
            if (! $batch) {
                return;
            }
            try {
                $affected = DB::table('laundrycard_transactions')->insertOrIgnore($batch);
                $inserted += $affected;
                $duplicates += count($batch) - $affected;
            } catch (Throwable $e) {
                $this->logger->error('transactions.insert', $e, ['store_id' => $store->store_id]);
            }
            $batch = [];
        };

        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $normalised = $this->normaliseRow($row, $store);
            if ($normalised === null) {
                continue;
            }
            $fieldsConfirmed = true;
            // Same transaction repeated inside one payload would break the bulk insert.
            $batch[$normalised['transaction_ref']] = $normalised;
            if (count($batch) >= self::INSERT_CHUNK) {
                $flush();
            }
        }
        $flush();

        return ['inserted' => $inserted, 'duplicate' => $duplicates, 'raw_count' => count($raw), 'fields_confirmed' => $fieldsConfirmed];
    }

    /**
     * Pull historical transactions for one store, in fixed-size windows, without
     * touching the incremental cursor. Safe to re-run (duplicates are ignored).
     *
     * @return array{inserted:int,duplicate:int,raw_count:int,windows:int}
     */
    public function backfillStore(LaundrycardStore $store, int $hours, ?callable $progress = null): array
    {
        $lock = Cache::lock(self::LOCK_KEY.$store->store_id, self::LOCK_TTL * 6);
        if (! $lock->get()) {
            return ['skipped' => 'lock_held'];
        }

        try {
            $end = now();
            $start = $end->copy()->subHours(max(1, $hours));
            $tot = ['inserted' => 0, 'duplicate' => 0, 'raw_count' => 0, 'windows' => 0];
            for ($from = $start->copy(); $from->lt($end); $from->addHours(self::BACKFILL_WINDOW_HOURS)) {
                $to = $from->copy()->addHours(self::BACKFILL_WINDOW_HOURS);
                if ($to->gt($end)) {
                    $to = $end->copy();
                }
                $w = $this->ingestWindow($store, $from->copy()->utc()->format('Y-m-d\TH:i:s'), $to->copy()->utc()->format('Y-m-d\TH:i:s'));
                $tot['inserted'] += $w['inserted'];
                $tot['duplicate'] += $w['duplicate'];
                $tot['raw_count'] += $w['raw_count'];
                $tot['windows']++;
                if ($progress) {
                    $progress($from, $w);
                }
            }
            $this->linkToSourceRecords($store);

            return $tot;
        } finally {
            $lock->release();
        }
    }

    /**
     * Normalise a raw API row into a DB-insertable array.
     * Returns null if the minimum required fields (card number + timestamp) cannot be extracted.
     */
    private function normaliseRow(array $row, LaundrycardStore $store): ?array
    {
        $cardNumber = $this->pick($row, self::FIELD_CANDIDATES['card_number']);
        $occurredAt = $this->pick($row, self::FIELD_CANDIDATES['occurred_at']);

        if (! filled($cardNumber) || ! filled($occurredAt)) {
            return null;
        }

        // Parse timestamp safely.
        try {
            $ts = CarbonImmutable::parse($occurredAt, 'UTC')->toDateTimeString();
        } catch (Throwable) {
            return null;
        }

        $txId       = $this->pick($row, self::FIELD_CANDIDATES['id']);
        $typeRaw    = $this->pick($row, self::FIELD_CANDIDATES['type_raw']);
        $amount     = $this->pickDecimal($row, self::FIELD_CANDIDATES['amount']);
        $bonus      = $this->pickDecimal($row, self::FIELD_CANDIDATES['bonus_amount']);
        $balAfter   = $this->pickDecimal($row, self::FIELD_CANDIDATES['balance_after']);
        $machNo     = $this->pick($row, self::FIELD_CANDIDATES['mach_no']);

        // Normalised type (empty until TYPE_MAP is populated after live confirmation).
        $typeNorm = isset($typeRaw) ? (self::TYPE_MAP[$typeRaw] ?? Tx::TYPE_UNKNOWN) : null;
        // If TYPE_MAP is empty, leave type as null to avoid fabricating 'unknown' on every row.
        if (empty(self::TYPE_MAP)) {
            $typeNorm = null;
        }

        // Dedup key: store + id (if present) + card + timestamp + raw_type.
        $ref = hash('sha256', implode('|', array_filter([
            $store->store_id,
            $txId !== null ? "id:{$txId}" : null,
            (string) $cardNumber,
            $ts,
            (string) $typeRaw,
        ])));

        $padded = Normalizer::storeId($store->store_id);
        $cardKey = (string) $cardNumber;
        if (! array_key_exists($cardKey, $this->recordIdCache)) {
            $this->recordIdCache[$cardKey] = Rec::where('laundrycard_store_id', $store->id)
                ->where('card_number', $cardKey)
                ->value('id');
        }
        $sourceRecord = $this->recordIdCache[$cardKey];

        return [
            'laundrycard_store_id'  => $store->id,
            'source_record_id'      => $sourceRecord,
            'card_number'           => (string) $cardNumber,
            'occurred_at'           => $ts,
            'transaction_type_raw'  => $typeRaw !== null ? (string) $typeRaw : null,
            'transaction_type'      => $typeNorm,
            'amount'                => $amount,
            'bonus_amount'          => $bonus,
            'balance_after'         => $balAfter,
            'mach_no'               => $machNo !== null ? (string) $machNo : null,
            'raw_payload'           => json_encode($row),
            'transaction_ref'       => $ref,
            'ingested_at'           => now()->toDateTimeString(),
            'created_at'            => now()->toDateTimeString(),
            'updated_at'            => now()->toDateTimeString(),
        ];
    }

    /**
     * After ingestion, link any transactions whose card has since been discovered.
     */
    private function linkToSourceRecords(LaundrycardStore $store): void
    {
        $unlinked = DB::table('laundrycard_transactions')
            ->where('laundrycard_store_id', $store->id)
            ->whereNull('source_record_id')
            ->pluck('card_number')
            ->unique();

        if ($unlinked->isEmpty()) {
            return;
        }

        $records = DB::table('laundrycard_source_records')
            ->where('laundrycard_store_id', $store->id)
            ->whereIn('card_number', $unlinked)
            ->pluck('id', 'card_number');

        foreach ($records as $cardNumber => $recordId) {
            DB::table('laundrycard_transactions')
                ->where('laundrycard_store_id', $store->id)
                ->where('card_number', $cardNumber)
                ->whereNull('source_record_id')
                ->update(['source_record_id' => $recordId]);
        }
    }

    // ------------------------------------------------------------------ field helpers

    /**
     * Case-insensitive field picker from a raw API row.
     * Tries each candidate name in order and returns the first match.
     */
    private function pick(array $row, array $candidates): mixed
    {
        foreach ($row as $k => $v) {
            foreach ($candidates as $candidate) {
                if (strcasecmp((string) $k, $candidate) === 0) {
                    return is_string($v) ? trim($v) : $v;
                }
            }
        }

        return null;
    }

    private function pickDecimal(array $row, array $candidates): ?string
    {
        $v = $this->pick($row, $candidates);
        if ($v === null || ! is_numeric($v) || ! is_finite((float) $v)) {
            return null;
        }

        return number_format((float) $v, 2, '.', '');
    }
}

