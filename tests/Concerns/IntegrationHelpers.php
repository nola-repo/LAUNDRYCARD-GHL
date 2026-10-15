<?php

namespace Tests\Concerns;

use App\Models\BranchMapping;
use App\Models\GhlConnection;
use App\Models\LaundrycardStore;
use App\Models\User;
use App\Services\BranchMappingService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

trait IntegrationHelpers
{
    protected function configureIntegration(): void
    {
        config([
            'integration.laundrycard.username' => 'api-user@example.test',
            'integration.laundrycard.password' => 'super-secret-lc-password',
            'integration.laundrycard.pos_mach_no' => null,
            'integration.ghl.client_id' => 'client-id-1',
            'integration.ghl.client_secret' => 'client-secret-SHOULD-NEVER-LEAK',
            'integration.ghl.redirect_uri' => 'https://app.example.test/oauth/ghl/callback',
            'integration.discovery.initial_import' => 'skip',
            'integration.branch_location_map' => [],
            'integration.sync.enabled' => true,
            'integration.cron_secret' => 'test-cron-secret-for-feature-tests',
        ]);
    }

    /** Http::fake() merges stubs (first match wins); tests that re-fake need a clean factory. */
    protected function httpFake(array $stubs = []): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake($stubs);
        Http::preventStrayRequests();
    }

    protected function admin(): User
    {
        $u = User::factory()->create();
        $u->is_admin = true;
        $u->save();

        return $u;
    }

    protected function store(string $id = '0160', string $name = 'The Laundry Room'): LaundrycardStore
    {
        return LaundrycardStore::create(['store_id' => $id, 'name' => $name, 'status' => 'active', 'first_seen_at' => now(), 'last_seen_at' => now()]);
    }

    protected function connection(string $locationId = 'LOC_A', string $name = 'Laundry Room of Lodi', array $over = []): GhlConnection
    {
        return GhlConnection::create($over + [
            'location_id' => $locationId, 'location_name' => $name, 'status' => 'active',
            'access_token' => 'ACCESS_'.$locationId, 'refresh_token' => 'REFRESH_'.$locationId,
            'expires_at' => now()->addHours(12), 'scopes' => ['contacts.readonly', 'contacts.write', 'locations.readonly'],
            'installed_at' => now(),
        ]);
    }

    protected function verifiedMapping(string $storeId = '0160', string $locationId = 'LOC_A', ?LaundrycardStore $store = null, ?GhlConnection $conn = null): BranchMapping
    {
        $store ??= LaundrycardStore::where('store_id', $storeId)->first() ?? $this->store($storeId);
        $conn ??= GhlConnection::where('location_id', $locationId)->first() ?? $this->connection($locationId);
        return BranchMapping::create([
            'laundrycard_store_id' => $store->id,
            'ghl_connection_id' => $conn->id,
            'status' => BranchMapping::VERIFIED,
            'origin' => 'test_fixture',
            'verified_at' => now(),
            'active_store_key' => $store->id,
            'active_connection_key' => $conn->id,
        ]);
    }

    protected function lcAuthFake(): array
    {
        return ['https://live.laundrycard.com/api/AuthToken' => Http::response(['Token' => 'LC_TOKEN_123'])];
    }

    /** @param list<array<string,mixed>> $cards */
    protected function lcFake(array $stores, array $cardsByStore): array
    {
        // Single catch-all closure to avoid Str::start('*') ambiguity.
        return [
            'https://live.laundrycard.com/*' => function (Request $r) use ($stores, $cardsByStore) {
                $path = parse_url($r->url(), PHP_URL_PATH);

                if (str_contains($path, '/api/AuthToken')) {
                    return Http::response(['Token' => 'LC_TOKEN_123']);
                }

                if (str_contains($path, '/api/stores')) {
                    return Http::response($stores);
                }

                if (str_contains($path, '/api/cards')) {
                    parse_str(parse_url($r->url(), PHP_URL_QUERY) ?? '', $q);
                    $id = str_pad((string) ($q['StoreID'] ?? ''), 4, '0', STR_PAD_LEFT);
                    $v = $cardsByStore[$id] ?? [];

                    return $v instanceof \Closure ? $v() : Http::response($v);
                }

                return Http::response([]);
            },
        ];
    }

    protected function query(Request $r): array
    {
        parse_str(parse_url($r->url(), PHP_URL_QUERY) ?? '', $q);

        return $q;
    }

    /** GHL fake: single dispatch closure — avoids Laravel's Str::start('*') prefix-wildcard issue. */
    protected function ghlFake(array $byEmail = [], array $byPhone = [], int $createStatus = 201, int $tokenStatus = 200): array
    {
        $n = 0;

        // One catch-all; dispatch by path+method ourselves to avoid the
        // Str::start('*', pattern) suffix-matching that makes every stub a wildcard.
        return [
            'https://services.leadconnectorhq.com/*' => function (Request $r) use ($byEmail, $byPhone, $createStatus, $tokenStatus, &$n) {
                $path = parse_url($r->url(), PHP_URL_PATH);

                // OAuth token refresh
                if (str_contains($path, '/oauth/token')) {
                    if ($tokenStatus >= 400) {
                        return Http::response(['error' => 'invalid_grant'], $tokenStatus);
                    }
                    return Http::response(['access_token' => 'NEW_AT', 'refresh_token' => 'NEW_RT', 'expires_in' => 3600]);
                }

                if (str_contains($path, '/contacts/search/duplicate')) {
                    parse_str(parse_url($r->url(), PHP_URL_QUERY) ?? '', $q);
                    if (isset($q['email'], $byEmail[$q['email']])) {
                        return Http::response(['contact' => $byEmail[$q['email']]]);
                    }
                    if (isset($q['number'], $byPhone[$q['number']])) {
                        return Http::response(['contact' => $byPhone[$q['number']]]);
                    }

                    return Http::response(['contact' => null]);
                }

                // Create contact: POST /contacts
                if ($r->method() === 'POST' && rtrim($path, '/') === '/contacts') {
                    if ($createStatus >= 400) {
                        return Http::response(['message' => 'boom'], $createStatus);
                    }
                    $n++;

                    return Http::response(['contact' => ['id' => 'CONTACT_'.$n] + $r->data()], 201);
                }

                // GET/PUT /contacts/{id}, /oauth/token, /locations/{id}, etc.
                return Http::response(['contact' => ['id' => 'X']]);
            },
        ];
    }

}
