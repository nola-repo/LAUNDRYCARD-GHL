<?php

namespace Tests\Feature;

use App\Models\ContactMapping;
use App\Models\GhlConnection;
use App\Models\IntegrationEvent;
use App\Models\LaundrycardSourceRecord as Rec;
use App\Models\LaundrycardStore;
use App\Models\SyncRun;
use App\Services\ContactSyncService;
use App\Services\CustomerDiscoveryService;
use App\Services\BranchMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\IntegrationHelpers;
use Tests\TestCase;

class DiscoveryAndSyncTest extends TestCase
{
    use IntegrationHelpers, RefreshDatabase;

    private array $stores = [
        ['ID' => 160, 'Name' => 'The Laundry Room'],
        ['ID' => 679, 'Name' => 'Laundry Time'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureIntegration();
        Cache::flush();
    }

    private function card(string $no, ?string $email, ?string $ts = null, int $store = 160): array
    {
        return ['CardNumber' => $no, 'StoreID' => $store, 'Status' => 1, 'Email' => $email, 'Timestamp' => $ts ?? now()->addMinute()->toIso8601ZuluString(), 'Balance' => 12.5];
    }

    private function ghlSent(string $needle = 'contacts'): \Illuminate\Support\Collection
    {
        return collect(Http::recorded())->filter(fn ($p) => str_contains($p[0]->url(), 'leadconnectorhq') && str_contains($p[0]->url(), $needle));
    }

    public function test_new_store_is_discovered_and_flagged_unmapped_and_never_auto_mapped(): void
    {
        $this->httpFake($this->lcFake($this->stores, []));
        $r = app(BranchMappingService::class)->refreshStores(app(\App\Services\LaundryCardClient::class));

        $this->assertSame(2, $r['new']);
        $this->assertSame(['0160', '0679'], LaundrycardStore::orderBy('store_id')->pluck('store_id')->all()); // leading zeros preserved
        $this->assertCount(2, app(BranchMappingService::class)->unmappedStores());
        $this->assertSame(0, \App\Models\BranchMapping::count());
        $this->assertTrue(IntegrationEvent::where('stage', 'stores.discovered')->where('outcome', 'unmapped')->exists());
    }

    public function test_initial_discovery_baselines_without_importing_history(): void
    {
        $this->verifiedMapping('0160', 'LOC_A');
        $this->httpFake($this->lcFake([['ID' => 160, 'Name' => 'The Laundry Room']], ['0160' => [
            $this->card('111', 'old1@example.test', '2020-01-01T00:00:00Z'), $this->card('222', 'old2@example.test', '2021-01-01T00:00:00Z'),
        ]]) + $this->ghlFake());

        $run = app(CustomerDiscoveryService::class)->run();

        $this->assertSame('completed', $run->status);
        $this->assertSame(2, Rec::where('processing_status', Rec::BASELINE_SKIPPED)->count());
        $this->assertSame(0, Rec::where('processing_status', Rec::PENDING)->count());
        $this->assertCount(0, $this->ghlSent(), 'Historical customers must not reach GHL.');
        $this->assertNotNull(LaundrycardStore::first()->baseline_completed_at);
    }

    public function test_verified_branch_routes_new_customer_to_the_correct_location_only(): void
    {
        $a = $this->verifiedMapping('0160', 'LOC_A');
        $b = $this->verifiedMapping('0679', 'LOC_B', null, $this->connection('LOC_B', 'Laundry Time Lakewood'));
        $storesPayload = $this->stores;
        $cards = ['0160' => [$this->card('111', 'old@example.test', '2020-01-01T00:00:00Z')], '0679' => [$this->card('333', 'old3@example.test', '2020-01-01T00:00:00Z', 679)]];
        $this->httpFake($this->lcFake($storesPayload, $cards) + $this->ghlFake());
        $svc = app(CustomerDiscoveryService::class);
        $svc->run(); // baseline

        // now a brand new customer at store 0679 only
        $cards['0679'][] = $this->card('444', 'new.customer@example.test', null, 679);
        $this->httpFake($this->lcFake($storesPayload, $cards) + $this->ghlFake());
        $svc->run();

        $rec = Rec::where('card_number', '444')->firstOrFail();
        $this->assertSame(Rec::CONFIRMED_NEW, $rec->classification);
        $this->assertSame(Rec::SYNCED_CREATED, $rec->processing_status);
        $this->assertSame('LOC_B', $rec->destination_location_id);

        $creates = $this->ghlSent('/contacts')->filter(fn ($p) => $p[0]->method() === 'POST');
        $this->assertCount(1, $creates);
        $req = $creates->first()[0];
        $this->assertSame('LOC_B', $req['locationId']);
        $this->assertSame('Bearer ACCESS_LOC_B', $req->header('Authorization')[0]);
        $this->assertSame('new.customer@example.test', $req['email']);
        $this->assertSame(0, Rec::where('card_number', '111')->where('processing_status', '!=', Rec::BASELINE_SKIPPED)->count());
    }

    public function test_unmapped_branch_never_sends_contact_anywhere(): void
    {
        $this->connection('LOC_A'); // an authorized location exists but is NOT mapped to store 0679
        $cards = ['0679' => [$this->card('1', 'old@example.test', '2020-01-01T00:00:00Z', 679)]];
        $this->httpFake($this->lcFake([['ID' => 679, 'Name' => 'Laundry Time']], $cards) + $this->ghlFake());
        $svc = app(CustomerDiscoveryService::class);
        $svc->run();
        $cards['0679'][] = $this->card('2', 'new@example.test', null, 679);
        $this->httpFake($this->lcFake([['ID' => 679, 'Name' => 'Laundry Time']], $cards) + $this->ghlFake());
        $svc->run();

        $rec = Rec::where('card_number', '2')->firstOrFail();
        $this->assertSame(Rec::HELD, $rec->processing_status);
        $this->assertSame('no_mapping', $rec->hold_reason);
        $this->assertCount(0, $this->ghlSent());

        // Once an admin verifies the mapping the held record is released.
        $store = LaundrycardStore::where('store_id', '0679')->first();
        $m = app(BranchMappingService::class)->assign($store, GhlConnection::first(), null);
        app(BranchMappingService::class)->verify($m, null);
        $this->httpFake($this->ghlFake());
        app(CustomerDiscoveryService::class)->dispatchPending($store);
        $this->assertSame(Rec::SYNCED_CREATED, $rec->fresh()->processing_status);
        $this->assertSame('LOC_A', $rec->fresh()->destination_location_id);
    }

    public function test_proposed_mapping_does_not_route(): void
    {
        $store = $this->store();
        app(BranchMappingService::class)->assign($store, $this->connection('LOC_A'), null);
        $d = app(BranchMappingService::class)->resolve($store);
        $this->assertFalse($d['routable']);
        $this->assertSame('mapping_not_verified', $d['reason']);
    }

    public function test_unconfirmed_new_cards_without_timestamp_proof_are_held_for_review(): void
    {
        $this->verifiedMapping('0160', 'LOC_A');
        $cards = ['0160' => [$this->card('1', 'a@example.test', '2020-01-01T00:00:00Z')]];
        $this->httpFake($this->lcFake([['ID' => 160, 'Name' => 'x']], $cards) + $this->ghlFake());
        $svc = app(CustomerDiscoveryService::class);
        $svc->run();
        $c = $this->card('2', 'b@example.test', '2019-01-01T00:00:00Z'); // appears now but timestamp says old
        $cards['0160'][] = $c;
        $this->httpFake($this->lcFake([['ID' => 160, 'Name' => 'x']], $cards) + $this->ghlFake());
        $svc->run();

        $rec = Rec::where('card_number', '2')->first();
        $this->assertSame(Rec::NEW_OBSERVED, $rec->classification);
        $this->assertSame(Rec::HELD, $rec->processing_status);
        $this->assertSame('unconfirmed_new_customer', $rec->hold_reason);
        $this->assertCount(0, $this->ghlSent());
    }

    public function test_reprocessing_never_duplicates_contacts(): void
    {
        $this->verifiedMapping('0160', 'LOC_A');
        $rec = $this->pendingRecord('555', 'dup@example.test');
        $this->httpFake($this->ghlFake());
        $sync = app(ContactSyncService::class);

        $sync->process($rec);
        $again = $sync->process($rec->fresh());
        // Forced reset (as an admin retry would do) still must not create a second contact.
        $rec->fresh()->update(['processing_status' => Rec::FAILED]);
        $this->httpFake($this->ghlFake(['dup@example.test' => ['id' => 'CONTACT_1', 'email' => 'dup@example.test']]));
        $sync->process($rec->fresh());

        $this->assertSame('already_synced', $again['outcome']);
        $this->assertSame(1, ContactMapping::count());
        $this->assertSame('CONTACT_1', ContactMapping::first()->ghl_contact_id);
        $this->assertCount(0, $this->ghlSent('/contacts')->filter(fn ($p) => $p[0]->method() === 'POST'));
    }

    public function test_existing_contact_is_updated_safely_not_duplicated(): void
    {
        $this->verifiedMapping('0160', 'LOC_A');
        $rec = $this->pendingRecord('556', 'exists@example.test', 'Jane Doe');
        $this->httpFake($this->ghlFake(['exists@example.test' => ['id' => 'EXISTING', 'email' => 'exists@example.test', 'firstName' => 'Janet', 'lastName' => null, 'tags' => ['vip']]]));

        $r = app(ContactSyncService::class)->process($rec);

        $this->assertSame('updated', $r['outcome']);
        $this->assertSame('EXISTING', ContactMapping::first()->ghl_contact_id);
        $put = $this->ghlSent('/contacts/EXISTING')->first()[0];
        $this->assertSame('PUT', $put->method());
        $this->assertArrayNotHasKey('firstName', $put->data(), 'Existing values are never overwritten.');
        $this->assertSame('Doe', $put['lastName']);
        $this->assertEqualsCanonicalizing(['vip', 'laundrycard'], $put['tags']);
        $this->assertCount(0, $this->ghlSent()->filter(fn ($p) => $p[0]->method() === 'POST'));
    }

    public function test_conflicting_email_and_phone_matches_are_held_not_merged(): void
    {
        $this->verifiedMapping('0160', 'LOC_A');
        $rec = $this->pendingRecord('557', 'both@example.test', 'Bob', '(973) 555-0101');
        $this->httpFake($this->ghlFake(
            ['both@example.test' => ['id' => 'C_EMAIL', 'email' => 'both@example.test']],
            ['+19735550101' => ['id' => 'C_PHONE', 'phone' => '+19735550101']],
        ));

        $r = app(ContactSyncService::class)->process($rec);

        $this->assertSame('held', $r['outcome']);
        $this->assertSame('conflicting_contacts', $rec->fresh()->hold_reason);
        $this->assertSame(0, ContactMapping::count());
        $this->assertCount(0, $this->ghlSent()->filter(fn ($p) => in_array($p[0]->method(), ['POST', 'PUT'])));
    }

    public function test_ghl_failure_is_recorded_and_safely_retryable(): void
    {
        $this->verifiedMapping('0160', 'LOC_A');
        $rec = $this->pendingRecord('558', 'retry@example.test');
        $this->httpFake($this->ghlFake([], [], 503));

        try {
            app(ContactSyncService::class)->process($rec);
            $this->fail('expected retryable exception');
        } catch (\App\Exceptions\Integration\RetryableException $e) {
            $this->assertSame(503, $e->httpStatus);
        }
        $this->assertSame(Rec::FAILED, $rec->fresh()->processing_status);
        $attempt = $rec->attempts()->first();
        $this->assertTrue($attempt->retry_eligible);
        $this->assertSame(503, $attempt->http_status);
        $this->assertSame(0, ContactMapping::count());

        $this->httpFake($this->ghlFake());
        $r = app(ContactSyncService::class)->process($rec->fresh());
        $this->assertSame('created', $r['outcome']);
        $this->assertSame(1, ContactMapping::count());
        $this->assertSame(2, $rec->fresh()->attempt_count);
    }

    public function test_revoked_credentials_hold_records_and_reconnection_releases_them(): void
    {
        $this->verifiedMapping('0160', 'LOC_A');
        $conn = GhlConnection::first();
        $conn->update(['expires_at' => now()->subMinute()]);
        $rec = $this->pendingRecord('559', 'rev@example.test');
        $this->httpFake($this->ghlFake(tokenStatus: 400));

        $r = app(ContactSyncService::class)->process($rec);

        $this->assertSame('held', $r['outcome']);
        $this->assertSame('connection_unusable', $rec->fresh()->hold_reason);
        $this->assertSame('revoked', $conn->fresh()->status);
        $this->assertCount(0, $this->ghlSent());
        $this->assertSame(1, \App\Models\BranchMapping::where('status', 'verified')->count(), 'Mapping survives revocation');

        // reinstall = same row active again with fresh tokens
        // Must reload from DB — the in-memory $conn still has status='active' from before
        // revocation, so a direct update() would not mark status as dirty.
        GhlConnection::where('id', $conn->id)->update([
            'status' => 'active', 'access_token' => encrypt('AT2'),
            'refresh_token' => encrypt('RT2'), 'expires_at' => now()->addDay(),
        ]);
        $this->httpFake($this->ghlFake());
        app(CustomerDiscoveryService::class)->dispatchPending();
        $this->assertSame(Rec::SYNCED_CREATED, $rec->fresh()->processing_status);
    }

    public function test_laundrycard_downtime_does_not_lose_progress(): void
    {
        $this->verifiedMapping('0160', 'LOC_A');
        $stores = [['ID' => 160, 'Name' => 'The Laundry Room']];
        $this->httpFake($this->lcFake($stores, ['0160' => fn () => Http::response('down', 503)]));
        $svc = app(CustomerDiscoveryService::class);

        $run = $svc->run();
        $this->assertSame('failed', $run->status);
        $store = LaundrycardStore::first();
        $this->assertNull($store->baseline_completed_at, 'baseline must not complete after a failed scan');
        $this->assertSame('failed', $store->last_scan_status);
        $this->assertSame(0, Rec::count());

        $this->httpFake($this->lcFake($stores, ['0160' => [$this->card('1', 'a@example.test', '2020-01-01T00:00:00Z')]]));
        $run = $svc->run();
        $this->assertSame('completed', $run->status);
        $this->assertSame(1, Rec::count());
        $this->assertNotNull($store->fresh()->baseline_completed_at);
    }

    public function test_expired_laundrycard_token_reauthenticates_once(): void
    {
        $auth = 0;
        $this->httpFake([
            'https://live.laundrycard.com/*' => function (Request $r) use (&$auth) {
                $path = parse_url($r->url(), PHP_URL_PATH);
                if (str_contains($path, '/api/AuthToken')) {
                    $auth++;

                    return Http::response(['Token' => 'T'.$auth]);
                }
                // /api/stores — reject first token, accept second
                return $r->header('Authorization')[0] === 'Bearer T1'
                    ? Http::response('', 401)
                    : Http::response([['ID' => 160, 'Name' => 'A']]);
            },
        ]);
        $stores = app(\App\Services\LaundryCardClient::class)->stores();
        $this->assertSame('0160', $stores[0]['store_id']);
        $this->assertSame(2, $auth);
    }

    public function test_read_only_scan_persists_nothing_and_never_calls_ghl(): void
    {
        $this->verifiedMapping('0160', 'LOC_A');
        $this->httpFake($this->lcFake([['ID' => 160, 'Name' => 'x']], ['0160' => [$this->card('1', 'a@example.test')]]) + $this->ghlFake());

        $run = app(CustomerDiscoveryService::class)->run(true);

        $this->assertSame('read_only', $run->mode);
        $this->assertSame(0, Rec::count());
        $this->assertNull(LaundrycardStore::first()->baseline_completed_at);
        $this->assertCount(0, $this->ghlSent());
    }

    public function test_logs_never_contain_tokens_secrets_or_raw_personal_data(): void
    {
        $this->verifiedMapping('0160', 'LOC_A');
        $rec = $this->pendingRecord('777', 'private.person@example.test', 'Private Person', '973-555-0199');
        $this->httpFake($this->ghlFake([], [], 503));
        try {
            app(ContactSyncService::class)->process($rec);
        } catch (\Throwable) {
        }
        app(\App\Services\IntegrationLogger::class)->log('test', ['message' => 'Bearer abc.def.ghi failed for private.person@example.test phone 973-555-0199', 'context' => ['access_token' => 'ACCESS_LOC_A', 'password' => 'super-secret-lc-password', 'email' => 'private.person@example.test']]);

        $dump = json_encode(IntegrationEvent::all()->toArray()).json_encode(\App\Models\SyncAttempt::all()->toArray());
        foreach (['ACCESS_LOC_A', 'REFRESH_LOC_A', 'super-secret-lc-password', 'client-secret-SHOULD-NEVER-LEAK', 'private.person@example.test', '973-555-0199', 'Private Person', 'abc.def.ghi'] as $needle) {
            $this->assertStringNotContainsString($needle, $dump, "Leaked: $needle");
        }
    }

    private function pendingRecord(string $card, string $email, ?string $name = null, ?string $phone = null): Rec
    {
        $store = LaundrycardStore::where('store_id', '0160')->first();
        $e = \App\Support\Normalizer::email($email);
        $p = \App\Support\Normalizer::phone($phone);

        return Rec::create([
            'laundrycard_store_id' => $store->id, 'card_number' => $card, 'first_observed_at' => now(), 'last_observed_at' => now(),
            'classification' => Rec::CONFIRMED_NEW, 'processing_status' => Rec::PENDING,
            'customer_name' => $name, 'customer_email' => $e, 'customer_phone' => $p,
            'email_hash' => \App\Support\Normalizer::hash($e), 'phone_hash' => \App\Support\Normalizer::hash($p),
        ]);
    }
}
