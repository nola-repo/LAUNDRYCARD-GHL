<?php

namespace Tests\Feature;

use App\Models\LaundrycardSourceRecord as Rec;
use App\Models\LaundrycardStore;
use App\Services\EnrichmentService;
use App\Services\LaundryCardClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\IntegrationHelpers;
use Tests\TestCase;

class MachNoConfigTest extends TestCase
{
    use IntegrationHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureIntegration();
        Cache::flush();
    }

    // ------------------------------------------------------------------ machNoForStore()

    public function test_per_store_mach_no_takes_priority_over_global(): void
    {
        config([
            'integration.laundrycard.pos_mach_no'       => 'GLOBAL_001',
            'integration.laundrycard.pos_mach_nos.0160' => 'STORE_160',
        ]);

        $lc = app(LaundryCardClient::class);
        $this->assertSame('STORE_160', $lc->machNoForStore('0160'));
        $this->assertSame('STORE_160', $lc->machNoForStore(160));
    }

    public function test_global_mach_no_used_when_no_per_store_override(): void
    {
        config([
            'integration.laundrycard.pos_mach_no'  => 'GLOBAL_001',
            'integration.laundrycard.pos_mach_nos' => [],
        ]);

        $lc = app(LaundryCardClient::class);
        $this->assertSame('GLOBAL_001', $lc->machNoForStore('0679'));
    }

    public function test_null_returned_when_no_mach_no_configured(): void
    {
        config([
            'integration.laundrycard.pos_mach_no'  => null,
            'integration.laundrycard.pos_mach_nos' => [],
        ]);

        $lc = app(LaundryCardClient::class);
        $this->assertNull($lc->machNoForStore('0160'));
        $this->assertFalse($lc->hasMachNo('0160'));
    }

    public function test_has_mach_no_returns_true_when_global_set(): void
    {
        config(['integration.laundrycard.pos_mach_no' => 'GLOBAL_001']);

        $lc = app(LaundryCardClient::class);
        $this->assertTrue($lc->hasMachNo('1153'));
    }

    // ------------------------------------------------------------------ account() uses per-store MachNo

    public function test_account_uses_per_store_mach_no_in_request_path(): void
    {
        config([
            'integration.laundrycard.pos_mach_no'       => 'GLOBAL_001',
            'integration.laundrycard.pos_mach_nos.0160' => 'STORE_MACH_160',
        ]);

        $capturedPath = null;

        $this->httpFake([
            'https://live.laundrycard.com/*' => function (Request $r) use (&$capturedPath) {
                $capturedPath = parse_url($r->url(), PHP_URL_PATH);
                if (str_contains($capturedPath, 'AuthToken')) {
                    return Http::response(['Token' => 'TOK']);
                }

                return Http::response([
                    'Name' => 'Jane Doe', 'Email' => 'jane@example.test', 'Phone' => '5041112222', 'Balance' => 15.00,
                ]);
            },
        ]);

        $lc = app(LaundryCardClient::class);
        $result = $lc->account('0160', 'CARD123');

        $this->assertNotNull($result);
        $this->assertStringContainsString('STORE_MACH_160', $capturedPath);
        $this->assertStringNotContainsString('GLOBAL_001', $capturedPath);
    }

    public function test_account_returns_null_immediately_when_no_mach_no(): void
    {
        config([
            'integration.laundrycard.pos_mach_no'  => null,
            'integration.laundrycard.pos_mach_nos' => [],
        ]);

        Http::preventStrayRequests(); // No request should be made.

        $lc = app(LaundryCardClient::class);
        $this->assertNull($lc->account('0160', 'CARD123'));
    }

    public function test_account_returns_null_on_404(): void
    {
        config(['integration.laundrycard.pos_mach_nos.0160' => 'MACH_001']);

        $this->httpFake([
            'https://live.laundrycard.com/*' => function (Request $r) {
                if (str_contains($r->url(), 'AuthToken')) {
                    return Http::response(['Token' => 'TOK']);
                }

                return Http::response([], 404);
            },
        ]);

        $lc = app(LaundryCardClient::class);
        $this->assertNull($lc->account('0160', 'MISSING_CARD'));
    }

    // ------------------------------------------------------------------ configDiagnostics()

    public function test_config_diagnostics_reports_missing_mach_nos(): void
    {
        config([
            'integration.laundrycard.pos_mach_no'       => null,
            'integration.laundrycard.pos_mach_nos.0160' => 'MACH_160',
            'integration.laundrycard.pos_mach_nos.0679' => null,
        ]);

        $lc   = app(LaundryCardClient::class);
        $diag = $lc->configDiagnostics();

        $this->assertSame('set', $diag['pos_mach_per_store']['0160']);
        $this->assertSame('missing', $diag['pos_mach_per_store']['0679']);
        $this->assertSame('missing', $diag['pos_mach_per_store']['0705']);
        $this->assertFalse($diag['pos_mach_global']);
    }

    public function test_config_diagnostics_shows_global_fallback_status(): void
    {
        config([
            'integration.laundrycard.pos_mach_no'  => 'GLOBAL_001',
            'integration.laundrycard.pos_mach_nos' => [],
        ]);

        $lc   = app(LaundryCardClient::class);
        $diag = $lc->configDiagnostics();

        $this->assertTrue($diag['pos_mach_global']);
        foreach (['0160', '0679', '0705', '1153', '1179'] as $id) {
            $this->assertSame('global-fallback', $diag['pos_mach_per_store'][$id]);
        }
    }

    // ------------------------------------------------------------------ EnrichmentService

    public function test_enrichment_skips_store_with_no_mach_no(): void
    {
        config([
            'integration.laundrycard.pos_mach_no'  => null,
            'integration.laundrycard.pos_mach_nos' => [],
            'integration.discovery.enrich_with_account_detail' => true,
        ]);

        $store = $this->store('0160');

        Http::preventStrayRequests();

        $svc    = app(EnrichmentService::class);
        $result = $svc->enrichStore($store);

        $this->assertTrue($result['no_mach']);
        $this->assertSame(0, $result['enriched']);
    }

    public function test_enrichment_skips_fully_identified_records(): void
    {
        config([
            'integration.laundrycard.pos_mach_nos.0160' => 'MACH_001',
            'integration.discovery.enrich_with_account_detail' => true,
        ]);

        $store = $this->store('0160');
        $record = Rec::create([
            'laundrycard_store_id' => $store->id,
            'card_number'          => 'FULL_CARD',
            'customer_name'        => 'Full Name',
            'customer_email'       => 'full@example.test',
            'customer_phone'       => '+15041112222',
            'email_hash'           => \App\Support\Normalizer::hash('full@example.test'),
            'phone_hash'           => \App\Support\Normalizer::hash('+15041112222'),
            'classification'       => Rec::NEW_OBSERVED,
            'processing_status'    => Rec::PENDING,
            'balance'              => '15.00',
            'eligibility_status'   => Rec::BALANCE_ELIGIBLE,
            'eligibility_reason'   => 'positive_balance',
            'first_observed_at'    => now(),
            'last_observed_at'     => now(),
        ]);

        Http::preventStrayRequests();

        $svc    = app(EnrichmentService::class);
        $result = $svc->enrichStore($store);

        // Fully identified records are excluded from the candidate query entirely, and enrichRecord returns skipped
        $this->assertSame(0, $result['enriched']);
        $this->assertSame('skipped', $svc->enrichRecord($store, $record));
    }

    public function test_enrichment_applies_account_data_to_anonymous_record(): void
    {
        config([
            'integration.laundrycard.pos_mach_nos.0160' => 'MACH_001',
            'integration.discovery.enrich_with_account_detail' => true,
        ]);

        $store = $this->store('0160');
        $record = Rec::create([
            'laundrycard_store_id' => $store->id,
            'card_number'          => 'ANON001',
            'classification'       => Rec::NEW_OBSERVED,
            'processing_status'    => Rec::PENDING,
            'balance'              => '12.50',
            'eligibility_status'   => Rec::BALANCE_ELIGIBLE,
            'eligibility_reason'   => 'positive_balance',
            'first_observed_at'    => now(),
            'last_observed_at'     => now(),
        ]);

        $this->httpFake([
            'https://live.laundrycard.com/*' => function (Request $r) {
                if (str_contains($r->url(), 'AuthToken')) {
                    return Http::response(['Token' => 'TOK']);
                }

                return Http::response([
                    'Name' => 'John Doe', 'Email' => 'john@example.test', 'Phone' => '5041112222', 'Balance' => 12.50,
                ]);
            },
        ]);

        $svc    = app(EnrichmentService::class);
        $result = $svc->enrichStore($store);

        $this->assertSame(1, $result['enriched']);
        $record->refresh();
        $this->assertSame('John Doe', $record->customer_name);
        $this->assertSame('john@example.test', $record->customer_email);
    }

    public function test_enrichment_does_not_overwrite_existing_email(): void
    {
        config([
            'integration.laundrycard.pos_mach_nos.0160' => 'MACH_001',
            'integration.discovery.enrich_with_account_detail' => true,
        ]);

        $store = $this->store('0160');
        $record = Rec::create([
            'laundrycard_store_id' => $store->id,
            'card_number'          => 'PARTIAL001',
            'customer_email'       => 'existing@example.test', // already set
            'email_hash'           => \App\Support\Normalizer::hash('existing@example.test'),
            'classification'       => Rec::NEW_OBSERVED,
            'processing_status'    => Rec::PENDING,
            'balance'              => '12.50',
            'eligibility_status'   => Rec::BALANCE_ELIGIBLE,
            'eligibility_reason'   => 'positive_balance',
            'first_observed_at'    => now(),
            'last_observed_at'     => now(),
        ]);

        $this->httpFake([
            'https://live.laundrycard.com/*' => function (Request $r) {
                if (str_contains($r->url(), 'AuthToken')) {
                    return Http::response(['Token' => 'TOK']);
                }

                return Http::response([
                    'Name' => 'Different Name', 'Email' => 'different@example.test', 'Balance' => 12.50,
                ]);
            },
        ]);

        $svc    = app(EnrichmentService::class);
        $svc->enrichStore($store);

        $record->refresh();
        // Existing email must be preserved
        $this->assertSame('existing@example.test', $record->customer_email);
        // Name gets filled in (was null)
        $this->assertSame('Different Name', $record->customer_name);
    }

    public function test_enrichment_handles_rate_limit_cooldown(): void
    {
        config([
            'integration.laundrycard.pos_mach_nos.0160' => 'MACH_001',
            'integration.discovery.enrich_with_account_detail' => true,
        ]);

        $store = $this->store('0160');
        $record = Rec::create([
            'laundrycard_store_id' => $store->id,
            'card_number'          => 'RATE001',
            'classification'       => Rec::NEW_OBSERVED,
            'processing_status'    => Rec::PENDING,
            'balance'              => '5.00',
            'eligibility_status'   => Rec::BALANCE_ELIGIBLE,
            'eligibility_reason'   => 'positive_balance',
            'first_observed_at'    => now(),
            'last_observed_at'     => now(),
        ]);

        $this->httpFake([
            'https://live.laundrycard.com/*' => function (Request $r) {
                if (str_contains($r->url(), 'AuthToken')) {
                    return Http::response(['Token' => 'TOK']);
                }

                return Http::response(['message' => 'Rate limit'], 429);
            },
        ]);

        $svc    = app(EnrichmentService::class);
        $result = $svc->enrichStore($store);

        // After 429, store should be on cooldown — no crash, just rate_limited return.
        $this->assertSame(0, $result['enriched']);
        $this->assertTrue(Cache::has('laundrycard:pos_mach_rate_limit:0160'));
    }

    public function test_discovery_continues_when_one_card_enrichment_fails(): void
    {
        // This tests the broader guarantee that EnrichmentService never throws out.
        config([
            'integration.laundrycard.pos_mach_nos.0160' => 'MACH_001',
            'integration.discovery.enrich_with_account_detail' => true,
        ]);

        $store = $this->store('0160');
        $callCount = 0;

        // Create 3 anonymous records; first fails, rest succeed.
        foreach (['FAIL001', 'OK002', 'OK003'] as $card) {
            Rec::create([
                'laundrycard_store_id' => $store->id,
                'card_number'          => $card,
                'classification'       => Rec::NEW_OBSERVED,
                'processing_status'    => Rec::PENDING,
                'balance'              => '5.00',
                'eligibility_status'   => Rec::BALANCE_ELIGIBLE,
                'eligibility_reason'   => 'positive_balance',
                'first_observed_at'    => now(),
                'last_observed_at'     => now(),
            ]);
        }

        $this->httpFake([
            'https://live.laundrycard.com/*' => function (Request $r) use (&$callCount) {
                if (str_contains($r->url(), 'AuthToken')) {
                    return Http::response(['Token' => 'TOK']);
                }
                $callCount++;

                return $callCount === 1
                    ? Http::response(['message' => 'Server Error'], 500)
                    : Http::response(['Name' => 'Good User', 'Email' => "user{$callCount}@test.com"]);
            },
        ]);

        $svc    = app(EnrichmentService::class);
        $result = $svc->enrichStore($store);

        // 1 failed, 2 enriched — service continues through the batch.
        $this->assertSame(1, $result['failed']);
        $this->assertSame(2, $result['enriched']);
    }
}

