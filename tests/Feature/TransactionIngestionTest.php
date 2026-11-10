<?php

namespace Tests\Feature;

use App\Models\LaundrycardSourceRecord as Rec;
use App\Models\LaundrycardTransaction as Tx;
use App\Services\TransactionDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\IntegrationHelpers;
use Tests\TestCase;

class TransactionIngestionTest extends TestCase
{
    use IntegrationHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureIntegration();
        config([
            'integration.transactions.enabled'            => true,
            'integration.transactions.window_hours'       => 1,
            'integration.transactions.overlap_minutes'    => 5,
            'integration.transactions.max_lookback_hours' => 24,
        ]);
        Cache::flush();
    }

    // ------------------------------------------------------------------ normalisation

    public function test_transactions_are_stored_with_raw_payload(): void
    {
        $store = $this->store('0160');
        $this->httpFake($this->lcAuthFake() + [
            'https://live.laundrycard.com/api/transact*' => Http::response([
                ['CardNumber' => 'CARD001', 'Timestamp' => '2026-11-09 10:00:00', 'TransactionType' => 'AddValue', 'Amount' => 20.00, 'Balance' => 32.50],
                ['CardNumber' => 'CARD002', 'Timestamp' => '2026-11-09 10:05:00', 'TransactionType' => 'Vend', 'Amount' => -2.50, 'Balance' => 10.00],
            ]),
        ]);

        $svc = app(TransactionDiscoveryService::class);
        $result = $svc->ingestStore($store);

        $this->assertSame(2, $result['inserted']);
        $this->assertSame(0, $result['duplicate']);
        $this->assertTrue($result['fields_confirmed']);

        $tx = Tx::where('card_number', 'CARD001')->first();
        $this->assertNotNull($tx);
        $this->assertSame('2026-11-09 10:00:00', $tx->occurred_at->toDateTimeString());
        $this->assertNotNull($tx->raw_payload); // raw payload preserved
        $this->assertSame('AddValue', $tx->transaction_type_raw);
        $this->assertSame(Tx::TYPE_UNKNOWN, $tx->transaction_type);
    }

    public function test_duplicate_transactions_are_silently_ignored(): void
    {
        $store = $this->store('0160');
        $txRow = ['CardNumber' => 'CARD001', 'Timestamp' => '2026-11-09 10:00:00', 'TransactionType' => 'AddValue', 'Amount' => 20.00, 'Balance' => 32.50];

        $this->httpFake($this->lcAuthFake() + [
            'https://live.laundrycard.com/api/transact*' => Http::response([$txRow]),
        ]);

        $svc = app(TransactionDiscoveryService::class);
        $svc->ingestStore($store);

        // Run again with same data — should produce 0 inserts, 1 duplicate.
        $this->httpFake($this->lcAuthFake() + [
            'https://live.laundrycard.com/api/transact*' => Http::response([$txRow]),
        ]);

        $result = $svc->ingestStore($store);
        $this->assertSame(0, $result['inserted']);
        $this->assertSame(1, $result['duplicate']);
        $this->assertSame(1, Tx::count()); // only one record in DB
    }

    public function test_transaction_cursor_advances_after_success(): void
    {
        $store = $this->store('0160');
        $this->assertNull($store->last_transaction_cursor_at);

        $this->httpFake($this->lcAuthFake() + [
            'https://live.laundrycard.com/api/transact*' => Http::response([]),
        ]);

        $svc = app(TransactionDiscoveryService::class);
        $svc->ingestStore($store);

        $store->refresh();
        $this->assertNotNull($store->last_transaction_cursor_at);
    }

    public function test_transaction_cursor_uses_min_datetime_param(): void
    {
        $store = $this->store('0160');
        $store->update(['last_transaction_cursor_at' => now()->subHours(2)->toDateTimeString()]);

        $capturedQuery = [];
        $this->httpFake($this->lcAuthFake() + [
            'https://live.laundrycard.com/api/transact*' => function (Request $r) use (&$capturedQuery) {
                parse_str(parse_url($r->url(), PHP_URL_QUERY) ?? '', $capturedQuery);

                return Http::response([]);
            },
        ]);

        $svc = app(TransactionDiscoveryService::class);
        $svc->ingestStore($store);

        $this->assertArrayHasKey('MinDateTime', $capturedQuery);
        $this->assertArrayHasKey('MaxDateTime', $capturedQuery);
        // Cursor was 2h ago minus overlap — MinDateTime should be before that
        $minDt = new \DateTime($capturedQuery['MinDateTime']);
        $this->assertLessThan(now()->subHours(1)->getTimestamp(), $minDt->getTimestamp());
    }

    public function test_transactions_associated_with_known_card_records(): void
    {
        $store = $this->store('0160');
        $record = Rec::create([
            'laundrycard_store_id' => $store->id,
            'card_number'          => 'KNOWN_CARD',
            'classification'       => Rec::NEW_OBSERVED,
            'processing_status'    => Rec::PENDING,
            'balance'              => '20.00',
            'eligibility_status'   => Rec::BALANCE_ELIGIBLE,
            'eligibility_reason'   => 'positive_balance',
            'first_observed_at'    => now(),
            'last_observed_at'     => now(),
        ]);

        $this->httpFake($this->lcAuthFake() + [
            'https://live.laundrycard.com/api/transact*' => Http::response([
                ['CardNumber' => 'KNOWN_CARD', 'Timestamp' => '2026-11-09 10:00:00', 'Amount' => 20.00],
            ]),
        ]);

        $svc = app(TransactionDiscoveryService::class);
        $svc->ingestStore($store);

        $tx = Tx::where('card_number', 'KNOWN_CARD')->first();
        $this->assertNotNull($tx);
        $this->assertSame($record->id, $tx->source_record_id);
    }

    public function test_transactions_for_unknown_card_stored_with_null_source_record(): void
    {
        $store = $this->store('0160');

        $this->httpFake($this->lcAuthFake() + [
            'https://live.laundrycard.com/api/transact*' => Http::response([
                ['CardNumber' => 'UNKNOWN_CARD', 'Timestamp' => '2026-11-09 11:00:00', 'Amount' => 5.00],
            ]),
        ]);

        $svc = app(TransactionDiscoveryService::class);
        $svc->ingestStore($store);

        $tx = Tx::where('card_number', 'UNKNOWN_CARD')->first();
        $this->assertNotNull($tx);
        $this->assertNull($tx->source_record_id);
    }

    public function test_rows_with_missing_card_number_or_timestamp_are_skipped(): void
    {
        $store = $this->store('0160');

        $this->httpFake($this->lcAuthFake() + [
            'https://live.laundrycard.com/api/transact*' => Http::response([
                ['Timestamp' => '2026-11-09 10:00:00', 'Amount' => 10.00],  // no card number
                ['CardNumber' => 'OK_CARD', 'Timestamp' => '2026-11-09 10:01:00', 'Amount' => 5.00], // valid
                ['CardNumber' => 'NO_TS_CARD', 'Amount' => 3.00],           // no timestamp
            ]),
        ]);

        $svc = app(TransactionDiscoveryService::class);
        $result = $svc->ingestStore($store);

        $this->assertSame(1, $result['inserted']); // only the valid row
    }

    public function test_ingestion_disabled_when_config_flag_is_false(): void
    {
        config(['integration.transactions.enabled' => false]);

        Http::preventStrayRequests();

        $svc    = app(TransactionDiscoveryService::class);
        $result = $svc->run();

        $this->assertArrayHasKey('skipped', $result);
        $this->assertSame('transaction_ingestion_disabled', $result['skipped']);
    }

    public function test_per_store_lock_prevents_concurrent_runs(): void
    {
        $store = $this->store('0160');

        // Simulate a lock already held.
        Cache::lock('integration:transaction_ingest:' . $store->store_id, 300)->get();

        $this->httpFake($this->lcAuthFake());

        $svc    = app(TransactionDiscoveryService::class);
        $result = $svc->ingestStore($store);

        $this->assertArrayHasKey('skipped', $result);
        $this->assertSame('lock_held', $result['skipped']);
    }

    public function test_store_failure_does_not_prevent_other_stores_from_ingesting(): void
    {
        $store1 = $this->store('0160');
        $store2 = $this->store('0679');

        $callCount = 0;
        $this->httpFake($this->lcAuthFake() + [
            'https://live.laundrycard.com/api/transact*' => function (Request $r) use (&$callCount) {
                $callCount++;
                parse_str(parse_url($r->url(), PHP_URL_QUERY) ?? '', $q);
                // Store 160 returns 500; store 679 succeeds.
                return (int) ($q['StoreID'] ?? 0) === 160
                    ? Http::response(['message' => 'Server Error'], 500)
                    : Http::response([['CardNumber' => 'C679', 'Timestamp' => '2026-11-09 10:00:00']]);
            },
        ]);

        $svc    = app(TransactionDiscoveryService::class);
        $result = $svc->run();

        $this->assertArrayHasKey('0160', $result['stores']);
        $this->assertArrayHasKey('0679', $result['stores']);
        $this->assertArrayHasKey('error', $result['stores']['0160']);
        $this->assertSame(1, $result['stores']['0679']['inserted']);
    }
}

