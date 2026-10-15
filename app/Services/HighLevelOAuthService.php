<?php

namespace App\Services;

use App\Exceptions\Integration\AuthorizationException;
use App\Exceptions\Integration\MalformedResponseException;
use App\Exceptions\Integration\RetryableException;
use App\Models\GhlConnection;
use App\Support\Sanitizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * GoHighLevel Marketplace OAuth 2.0 (Authorization Code Grant) lifecycle and the
 * persistent registry of authorised locations.
 */
class HighLevelOAuthService
{
    public function __construct(
        private IntegrationLogger $logger,
        private BranchMappingService $branchMapper,
    ) {
    }

    public function isConfigured(): bool
    {
        $c = config('integration.ghl');

        return filled($c['client_id']) && filled($c['client_secret']) && filled($c['redirect_uri']);
    }

    /** Build the install URL with a single-use, expiring state value. */
    public function authorizationUrl(?int $userId = null): string
    {
        if (! $this->isConfigured()) {
            throw new AuthorizationException('GHL OAuth client is not configured (GHL_CLIENT_ID / GHL_CLIENT_SECRET / GHL_REDIRECT_URI).', null, 'oauth.configure');
        }
        $state = Str::random(48);
        Cache::put($this->stateKey($state), ['user_id' => $userId, 'created_at' => now()->toIso8601String()], (int) config('integration.ghl.state_ttl_seconds'));

        $q = http_build_query([
            'response_type' => 'code',
            'redirect_uri' => config('integration.ghl.redirect_uri'),
            'client_id' => config('integration.ghl.client_id'),
            'scope' => implode(' ', config('integration.ghl.scopes')),
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);

        return config('integration.ghl.authorize_url').'?'.$q;
    }

    /**
     * Handle the redirect from GHL. Returns the registered connection.
     *
     * @param  array<string,mixed>  $query
     */
    public function handleCallback(array $query): GhlConnection
    {
        $this->logger->newCorrelation();

        if (! empty($query['error'])) {
            $this->logger->log('oauth.callback', [
                'level' => 'warning', 'outcome' => 'denied',
                'message' => 'Authorization denied/failed at provider: '.Sanitizer::string((string) $query['error'], 120),
            ]);
            throw new AuthorizationException('GoHighLevel returned an authorization error.', null, 'oauth.callback');
        }

        $state = (string) ($query['state'] ?? '');
        $code = (string) ($query['code'] ?? '');

        // 1. state: must exist, be unexpired and is consumed exactly once.
        if ($state === '' || Cache::pull($this->stateKey($state)) === null) {
            $this->logger->log('oauth.callback', ['level' => 'warning', 'outcome' => 'rejected', 'message' => 'Invalid, expired or reused OAuth state.']);
            throw new AuthorizationException('Invalid or expired OAuth state.', null, 'oauth.callback');
        }
        if ($code === '') {
            $this->logger->log('oauth.callback', ['level' => 'warning', 'outcome' => 'rejected', 'message' => 'Callback without authorization code.']);
            throw new AuthorizationException('Missing authorization code.', null, 'oauth.callback');
        }

        // 2. authorization codes are single use; refuse replays before calling the provider.
        if (! Cache::add('ghl_oauth_code:'.hash('sha256', $code), 1, 86400)) {
            $this->logger->log('oauth.callback', ['level' => 'warning', 'outcome' => 'rejected', 'message' => 'Authorization code reuse blocked.']);
            throw new AuthorizationException('Authorization code has already been used.', null, 'oauth.callback');
        }

        // 3. exchange
        $data = $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'user_type' => 'Location',
            'redirect_uri' => config('integration.ghl.redirect_uri'),
        ], 'oauth.exchange');

        $locationId = $data['locationId'] ?? null;
        if (($data['userType'] ?? 'Location') !== 'Location' || ! $locationId) {
            $this->logger->log('oauth.exchange', [
                'level' => 'warning', 'outcome' => 'unsupported',
                'message' => 'Token response was not location-scoped (agency install). Install the app on a sub-account.',
                'context' => ['user_type' => $data['userType'] ?? null],
            ]);
            throw new AuthorizationException('Only sub-account (Location) installations are supported.', null, 'oauth.exchange');
        }

        // 4. persist
        $connection = $this->persist($data);

        // 5. identify (best-effort; failure is non-fatal)
        $this->identifyLocation($connection);

        // 6. auto-map to the best-matching LaundryCard store (fully automated)
        try {
            $this->branchMapper->autoMapConnection($connection->fresh());
        } catch (Throwable $e) {
            $this->logger->log('mapping.auto', [
                'level'           => 'warning',
                'ghl_location_id' => $connection->location_id,
                'message'         => 'Auto-map attempt failed after install: '.\App\Support\Sanitizer::string($e->getMessage(), 200),
            ]);
        }

        return $connection->refresh();
    }

    /** Ensure the access token is valid for at least the configured leeway. Refreshes under a lock. */
    public function ensureFresh(GhlConnection $connection): GhlConnection
    {
        if ($connection->status === GhlConnection::REVOKED || $connection->status === GhlConnection::DISCONNECTED) {
            throw new AuthorizationException("GHL connection is {$connection->status}; reconnect required.", null, 'oauth.refresh');
        }
        $leeway = (int) config('integration.ghl.refresh_leeway');
        if ($connection->access_token && $connection->expires_at && $connection->expires_at->gt(now()->addSeconds($leeway))) {
            return $connection;
        }

        return $this->refresh($connection);
    }

    public function refresh(GhlConnection $connection): GhlConnection
    {
        $lock = Cache::lock('ghl_refresh:'.$connection->id, 30);
        try {
            $lock->block(10);
        } catch (Throwable) {
            throw new RetryableException('Another worker is refreshing this GHL token.', null, 'oauth.refresh', 5);
        }

        try {
            $connection->refresh();
            // Another worker may have refreshed while we waited.
            if ($connection->status === GhlConnection::ACTIVE && $connection->expires_at && $connection->expires_at->gt(now()->addSeconds((int) config('integration.ghl.refresh_leeway')))) {
                return $connection;
            }
            if (! $connection->refresh_token) {
                $this->markUnusable($connection, GhlConnection::REVOKED, 'No refresh token stored; reconnect required.');
                throw new AuthorizationException('No refresh token available; reconnect required.', null, 'oauth.refresh');
            }

            try {
                $data = $this->tokenRequest([
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $connection->refresh_token,
                    'user_type' => 'Location',
                    'redirect_uri' => config('integration.ghl.redirect_uri'),
                ], 'oauth.refresh', $connection);
            } catch (AuthorizationException $e) {
                $this->markUnusable($connection, GhlConnection::REVOKED, $e->getMessage());
                throw $e;
            }

            $connection->update([
                'access_token' => $data['access_token'],
                // GHL rotates refresh tokens; always store the latest one.
                'refresh_token' => $data['refresh_token'] ?? $connection->refresh_token,
                'expires_at' => now()->addSeconds((int) ($data['expires_in'] ?? 86400)),
                'scopes' => $this->parseScopes($data['scope'] ?? null) ?: $connection->scopes,
                'status' => GhlConnection::ACTIVE,
                'last_refreshed_at' => now(),
                'last_error' => null,
                'last_error_at' => null,
            ]);
            $this->logger->log('oauth.refresh', ['outcome' => 'success', 'ghl_location_id' => $connection->location_id, 'message' => 'Access token refreshed.']);

            return $connection;
        } finally {
            optional($lock)->release();
        }
    }

    public function markUnusable(GhlConnection $connection, string $status, string $reason): void
    {
        $connection->update([
            'status' => $status,
            'last_error' => Sanitizer::string($reason, 300),
            'last_error_at' => now(),
            'disconnected_at' => $status === GhlConnection::DISCONNECTED ? now() : $connection->disconnected_at,
        ]);
        $this->logger->log('oauth.status', [
            'level' => 'warning', 'outcome' => $status, 'ghl_location_id' => $connection->location_id,
            'message' => "Connection marked {$status}: ".$reason,
        ]);
    }

    public function disconnect(GhlConnection $connection): void
    {
        // Keep the row (and its mappings) so reinstalling restores the same record.
        $connection->update(['access_token' => null, 'refresh_token' => null, 'expires_at' => null]);
        $this->markUnusable($connection, GhlConnection::DISCONNECTED, 'Disconnected by administrator.');
    }

    /** Fetch the location name through the supported API. Best effort: failure never blocks registration. */
    public function identifyLocation(GhlConnection $connection): void
    {
        $started = microtime(true);
        try {
            $response = Http::baseUrl(config('integration.ghl.api_base'))
                ->acceptJson()->withToken($connection->access_token)
                ->withHeaders(['Version' => config('integration.ghl.api_version')])
                ->timeout((int) config('integration.ghl.timeout'))
                ->get('/locations/'.rawurlencode($connection->location_id));
        } catch (ConnectionException $e) {
            $this->logger->api('ghl', 'GET', '/locations/{id}', null, (int) ((microtime(true) - $started) * 1000), ['ghl_location_id' => $connection->location_id]);

            return;
        }
        $this->logger->api('ghl', 'GET', '/locations/{id}', $response->status(), (int) ((microtime(true) - $started) * 1000), ['ghl_location_id' => $connection->location_id]);

        $loc = $response->successful() ? ($response->json('location') ?? $response->json()) : null;
        if (is_array($loc) && ! empty($loc['name'])) {
            $connection->update([
                'location_name' => $loc['name'],
                'metadata' => array_merge($connection->metadata ?? [], array_filter([
                    'timezone' => $loc['timezone'] ?? null,
                    'city' => $loc['city'] ?? null,
                    'state' => $loc['state'] ?? null,
                ])),
                'last_success_at' => now(),
            ]);
        } else {
            $connection->update(['metadata' => array_merge($connection->metadata ?? [], ['name_lookup' => 'failed (check locations.readonly scope)'])]);
        }
    }

    // ------------------------------------------------------------------ internals

    private function persist(array $data): GhlConnection
    {
        return DB::transaction(function () use ($data) {
            $existing = GhlConnection::where('location_id', $data['locationId'])->lockForUpdate()->first();
            $attrs = [
                'company_id' => $data['companyId'] ?? null,
                'ghl_user_id' => $data['userId'] ?? null,
                'user_type' => $data['userType'] ?? 'Location',
                'status' => GhlConnection::ACTIVE,
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'expires_at' => now()->addSeconds((int) ($data['expires_in'] ?? 86400)),
                'scopes' => $this->parseScopes($data['scope'] ?? null),
                'last_error' => null,
                'last_error_at' => null,
                'disconnected_at' => null,
                'last_refreshed_at' => now(),
            ];

            if ($existing) {
                $existing->update($attrs + [
                    'install_count' => $existing->install_count + 1,
                    'last_reinstalled_at' => now(),
                ]);
                $connection = $existing;
                $event = 'reinstalled';
            } else {
                $connection = GhlConnection::create($attrs + ['location_id' => $data['locationId'], 'installed_at' => now()]);
                $event = 'installed';
            }

            $this->logger->log('oauth.install', [
                'outcome' => $event,
                'ghl_location_id' => $connection->location_id,
                'message' => "Location {$event}; automatic routing requires an exact configured branch association.",
                'context' => ['scopes' => $connection->scopes, 'install_count' => $connection->install_count],
            ]);

            return $connection;
        });
    }

    private function tokenRequest(array $params, string $stage, ?GhlConnection $connection = null): array
    {
        $started = microtime(true);
        try {
            $response = Http::asForm()->acceptJson()
                ->timeout((int) config('integration.ghl.timeout'))
                ->post(config('integration.ghl.token_url'), $params + [
                    'client_id' => config('integration.ghl.client_id'),
                    'client_secret' => config('integration.ghl.client_secret'),
                ]);
        } catch (ConnectionException $e) {
            $this->logger->api('ghl', 'POST', '/oauth/token', null, (int) ((microtime(true) - $started) * 1000), ['ghl_location_id' => $connection?->location_id]);
            throw new RetryableException('GHL token endpoint unreachable.', null, $stage);
        }
        $this->logger->api('ghl', 'POST', '/oauth/token', $response->status(), (int) ((microtime(true) - $started) * 1000), [
            'ghl_location_id' => $connection?->location_id,
            'context' => ['grant_type' => $params['grant_type']],
        ]);

        if ($response->status() === 429 || $response->status() >= 500) {
            throw new RetryableException("GHL token endpoint error (HTTP {$response->status()}).", $response->status(), $stage, (int) ($response->header('Retry-After') ?: 30));
        }
        if ($response->status() >= 400) {
            $err = Sanitizer::string((string) ($response->json('error_description') ?? $response->json('error') ?? $response->json('message') ?? 'rejected'), 150);
            throw new AuthorizationException("GHL rejected the token request: {$err}", $response->status(), $stage);
        }

        $data = $response->json();
        if (! is_array($data) || empty($data['access_token'])) {
            throw new MalformedResponseException('GHL token response was missing access_token.', $response->status(), $stage);
        }
        if ($params['grant_type'] === 'authorization_code' && empty($data['refresh_token'])) {
            throw new MalformedResponseException('GHL token response was missing refresh_token.', $response->status(), $stage);
        }

        return $data;
    }

    private function parseScopes(mixed $scope): array
    {
        if (is_array($scope)) {
            return array_values($scope);
        }

        return $scope ? array_values(array_filter(preg_split('/[\s,]+/', (string) $scope))) : [];
    }

    private function stateKey(string $state): string
    {
        return 'ghl_oauth_state:'.hash('sha256', $state);
    }
}
