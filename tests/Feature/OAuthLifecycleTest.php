<?php

namespace Tests\Feature;

use App\Models\GhlConnection;
use App\Models\IntegrationEvent;
use App\Services\BranchMappingService;
use App\Services\HighLevelOAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\IntegrationHelpers;
use Tests\TestCase;

class OAuthLifecycleTest extends TestCase
{
    use IntegrationHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureIntegration();
    }

    private function tokenResponse(string $loc, string $access = null): array
    {
        return [
            'access_token' => $access ?? 'AT_'.$loc, 'refresh_token' => 'RT_'.$loc, 'expires_in' => 86399,
            'scope' => 'contacts.readonly contacts.write locations.readonly', 'userType' => 'Location',
            'companyId' => 'COMP1', 'locationId' => $loc, 'userId' => 'U1',
        ];
    }

    private function install(string $loc, string $name): \Illuminate\Testing\TestResponse
    {
        $this->httpFake([
            'services.leadconnectorhq.com/oauth/token' => Http::response($this->tokenResponse($loc)),
            'services.leadconnectorhq.com/locations/*' => Http::response(['location' => ['id' => $loc, 'name' => $name]]),
        ]);
        $url = app(HighLevelOAuthService::class)->authorizationUrl();
        parse_str(parse_url($url, PHP_URL_QUERY), $q);

        return $this->get('/oauth/ghl/callback?code=CODE_'.$loc.'_'.uniqid().'&state='.$q['state']);
    }

    public function test_location_authorizes_and_is_registered_encrypted(): void
    {
        $this->install('LOC_A', 'Laundry Room of Lodi')->assertOk();

        $c = GhlConnection::where('location_id', 'LOC_A')->firstOrFail();
        $this->assertSame('active', $c->status);
        $this->assertSame('Laundry Room of Lodi', $c->location_name);
        $this->assertContains('contacts.write', $c->scopes);
        $this->assertNotNull($c->expires_at);
        // encrypted at rest
        $raw = \DB::table('ghl_connections')->where('id', $c->id)->first();
        $this->assertStringNotContainsString('AT_LOC_A', $raw->access_token);
        $this->assertStringNotContainsString('RT_LOC_A', $raw->refresh_token);
        $this->assertSame('AT_LOC_A', $c->access_token);
        $this->assertTrue(IntegrationEvent::where('stage', 'oauth.install')->where('ghl_location_id', 'LOC_A')->exists());
    }

    public function test_second_location_authorizes_without_code_changes_and_remains_unmapped(): void
    {
        $this->install('LOC_A', 'Laundry Room of Lodi');
        $this->install('LOC_B', 'Laundry Time Lakewood')->assertOk();

        $this->assertSame(2, GhlConnection::count());
        $unassigned = app(BranchMappingService::class)->unassignedConnections();
        $this->assertCount(2, $unassigned);
        $this->assertSame(0, \App\Models\BranchMapping::count(), 'No mapping may be created automatically.');
    }

    public function test_forged_state_is_rejected_and_no_token_exchange_happens(): void
    {
        $this->httpFake();
        $this->get('/oauth/ghl/callback?code=abc&state=forged')->assertStatus(400);
        Http::assertNothingSent();
        $this->assertSame(0, GhlConnection::count());
    }

    public function test_missing_state_and_state_replay_are_rejected(): void
    {
        $this->get('/oauth/ghl/callback?code=abc')->assertStatus(400);

        $this->httpFake(['services.leadconnectorhq.com/oauth/token' => Http::response($this->tokenResponse('LOC_A')), 'services.leadconnectorhq.com/locations/*' => Http::response([], 404)]);
        $url = app(HighLevelOAuthService::class)->authorizationUrl();
        parse_str(parse_url($url, PHP_URL_QUERY), $q);
        $this->get('/oauth/ghl/callback?code=c1&state='.$q['state'])->assertOk();
        $this->get('/oauth/ghl/callback?code=c2&state='.$q['state'])->assertStatus(400); // state is single use
    }

    public function test_authorization_code_reuse_is_blocked(): void
    {
        $this->httpFake(['services.leadconnectorhq.com/oauth/token' => Http::response($this->tokenResponse('LOC_A')), 'services.leadconnectorhq.com/locations/*' => Http::response([], 404)]);
        $svc = app(HighLevelOAuthService::class);
        foreach ([1, 2] as $i) {
            parse_str(parse_url($svc->authorizationUrl(), PHP_URL_QUERY), $q);
            $status = $this->get('/oauth/ghl/callback?code=SAME_CODE&state='.$q['state'])->status();
            $this->assertSame($i === 1 ? 200 : 400, $status);
        }
        Http::assertSentCount(2); // token + (failed) location lookup, only once
    }

    public function test_reinstall_updates_same_row_and_keeps_mapping(): void
    {
        $this->install('LOC_A', 'Laundry Room of Lodi');
        $store = $this->store();
        $mapping = $this->verifiedMapping('0160', 'LOC_A', $store);

        GhlConnection::where('location_id', 'LOC_A')->first()->update(['status' => 'revoked']);
        $this->install('LOC_A', 'Laundry Room of Lodi');

        $this->assertSame(1, GhlConnection::count());
        $c = GhlConnection::first();
        $this->assertSame('active', $c->status);
        $this->assertSame(2, $c->install_count);
        $this->assertSame('verified', $mapping->fresh()->status);
    }

    public function test_agency_level_token_is_refused(): void
    {
        $data = $this->tokenResponse('LOC_A');
        $data['userType'] = 'Company';
        unset($data['locationId']);
        $this->httpFake(['services.leadconnectorhq.com/oauth/token' => Http::response($data)]);
        parse_str(parse_url(app(HighLevelOAuthService::class)->authorizationUrl(), PHP_URL_QUERY), $q);
        $this->get('/oauth/ghl/callback?code=X&state='.$q['state'])->assertStatus(400);
        $this->assertSame(0, GhlConnection::count());
    }

    public function test_expiring_token_is_refreshed_and_refresh_token_rotates(): void
    {
        $c = $this->connection('LOC_A', 'A', ['expires_at' => now()->subMinute()]);
        $this->httpFake(['services.leadconnectorhq.com/oauth/token' => Http::response(['access_token' => 'NEW_AT', 'refresh_token' => 'NEW_RT', 'expires_in' => 3600, 'scope' => 'contacts.readonly contacts.write'])]);

        $fresh = app(HighLevelOAuthService::class)->ensureFresh($c);

        $this->assertSame('NEW_AT', $fresh->access_token);
        $this->assertSame('NEW_RT', $fresh->refresh_token);
        $this->assertTrue($fresh->expires_at->isFuture());
    }

    public function test_revoked_refresh_marks_connection_revoked_without_losing_mapping(): void
    {
        $this->verifiedMapping('0160', 'LOC_A');
        $c = GhlConnection::first();
        $c->update(['expires_at' => now()->subMinute()]);
        $this->httpFake(['services.leadconnectorhq.com/oauth/token' => Http::response(['error' => 'invalid_grant', 'error_description' => 'refresh token revoked'], 400)]);

        $this->expectException(\App\Exceptions\Integration\AuthorizationException::class);
        try {
            app(HighLevelOAuthService::class)->ensureFresh($c);
        } finally {
            $this->assertSame('revoked', $c->fresh()->status);
            $this->assertSame(1, \App\Models\BranchMapping::where('status', 'verified')->count());
        }
    }
}
