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

    public function test_card_reload_updates_synced_customer_balance_and_ghl_tags_in_real_time(): void
    {
        $this->verifiedMapping('0160', 'LOC_A');
        $store = \App\Models\LaundrycardStore::where('store_id', '0160')->first();
        $conn  = \App\Models\GhlConnection::where('location_id', 'LOC_A')->first();

        // Existing customer already synced to GHL with $8.00 balance (low tier)
        $record = Rec::create([
            'laundrycard_store_id'    => $store->id,
            'ghl_connection_id'       => $conn->id,
            'destination_location_id' => 'LOC_A',
            'card_number'             => 'RELOAD_CARD_99',
            'classification'          => Rec::NEW_OBSERVED,
            'processing_status'       => Rec::SYNCED_CREATED,
            'balance'                 => '8.00',
            'eligibility_status'      => Rec::BALANCE_ELIGIBLE,
            'eligibility_reason'      => 'positive_balance',
            'customer_name'           => 'Alex Rivera',
            'customer_email'          => 'alex@example.test',
            'email_hash'              => \App\Support\Normalizer::hash('alex@example.test'),
            'first_observed_at'       => now()->subDays(2),
            'last_observed_at'        => now()->subDays(2),
            'synced_at'               => now()->subDays(2),
        ]);

        \App\Models\ContactMapping::create([
            'source_record_id'  => $record->id,
            'ghl_connection_id' => $conn->id,
            'location_id'       => 'LOC_A',
            'ghl_contact_id'    => 'GHL_CONTACT_99',
            'action'            => 'created',
            'idempotency_key'   => hash('sha256', 'LOC_A|0160|RELOAD_CARD_99'),
            'synced_at'         => now()->subDays(2),
        ]);

        // Customer reloads $25.00 via credit card (TransType=3), bringing balance to $33.00
        $reloadTime = now()->utc()->subMinutes(5)->format('Y-m-d\TH:i:s');
        $this->httpFake($this->lcAuthFake() + [
            'https://live.laundrycard.com/api/transact*' => Http::response([
                [
                    'ID'          => 9001,
                    'CardNumber'  => 'RELOAD_CARD_99',
                    'DateTime'    => $reloadTime,
                    'TransType'   => '3',
                    'TransAmount' => 25.00,
                    'Balance'     => 33.00,
                    'MachNo'      => '1001',
                ],
            ]),
        ] + $this->ghlFake([
            'alex@example.test' => [
                'id'        => 'GHL_CONTACT_99',
                'email'     => 'alex@example.test',
                'firstName' => 'Alex',
                'lastName'  => 'Rivera',
                'tags'      => ['laundrycard', 'store-0160', 'balance-positive', 'balance-low'],
            ],
        ]));

        $svc    = app(TransactionDiscoveryService::class);
        $result = $svc->run('0160');

        $this->assertSame(1, $result['reloaded_queued']);

        $record->refresh();
        $this->assertSame('33.00', (string) $record->balance);
        $this->assertSame(Rec::SYNCED_UPDATED, $record->processing_status);

        $put = $this->ghlSent('/contacts/GHL_CONTACT_99')->filter(fn ($p) => $p[0]->method() === 'PUT')->first()[0];
        $this->assertSame('PUT', $put->method());
        $this->assertContains('laundrycard', $put['tags']);
        $this->assertContains('store-0160', $put['tags']);
        $this->assertContains('balance-positive', $put['tags']);
        $this->assertContains('balance-high', $put['tags']);
        $this->assertContains('reloaded-30d', $put['tags']);
        $this->assertContains('active-30d', $put['tags']);
        $this->assertNotContains('balance-low', $put['tags'], 'Stale low-balance tag is replaced by high-balance tag after reload');
    }

    public function test_offline_store_with_live_enabled_false_is_skipped_without_calling_api(): void
    {
        $online  = $this->store('0160');
        $offline = $this->store('0679');
        $offline->update(['live_enabled' => false]);

        $calledStores = [];
        $this->httpFake($this->lcAuthFake() + [
            'https://live.laundrycard.com/api/transact*' => function (Request $r) use (&$calledStores) {
                parse_str(parse_url($r->url(), PHP_URL_QUERY) ?? '', $q);
                $calledStores[] = (int) ($q['StoreID'] ?? 0);

                return Http::response([]);
            },
        ]);

        $svc    = app(TransactionDiscoveryService::class);
        $result = $svc->run();

        $this->assertSame([160], $calledStores, 'Offline store 0679 must be skipped so the API is not exhausted');
        $this->assertSame('store_live_disabled', $result['stores']['0679']['skipped'] ?? null);
    }
}

