<?php

namespace App\Services;

use App\Exceptions\Integration\AuthorizationException;
use App\Exceptions\Integration\IntegrationException;
use App\Exceptions\Integration\MalformedResponseException;
use App\Exceptions\Integration\RetryableException;
use App\Support\Normalizer;
use App\Support\Sanitizer;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Read-only client for the documented CCI LaundryCard Live WebAPI.
 *
 *  POST /api/AuthToken {UserName,Password} -> {Token}
 *  GET  /api/stores, /api/stores/{id}
 *  GET  /api/cards?StoreID=&MinCardNum=&MaxCardNum=
 *  GET  /api/transact?StoreID=&MinDateTime=&MaxDateTime=
 *  GET  /api/pos/{StoreID}/{MachNo}/Account/{Card}
 *
 * POST endpoints that mutate customers (Account update, AddValue, Vend) are
 * deliberately NOT implemented: the integration is one-way.
 */
class LaundryCardClient
{
    private const TOKEN_CACHE_KEY = 'laundrycard:api_token';

    public function __construct(private IntegrationLogger $logger)
    {
    }

    public function isConfigured(): bool
    {
        return filled(config('integration.laundrycard.username')) && filled(config('integration.laundrycard.password'));
    }

    public function authenticate(): string
    {
        if (! $this->isConfigured()) {
            throw new AuthorizationException('LaundryCard credentials are not configured (LAUNDRYCARD_USERNAME / LAUNDRYCARD_PASSWORD).', null, 'laundrycard.auth');
        }

        $started = microtime(true);
        try {
            $response = $this->baseRequest()->post('/api/AuthToken', [
                'UserName' => config('integration.laundrycard.username'),
                'Password' => config('integration.laundrycard.password'),
            ]);
        } catch (ConnectionException $e) {
            $this->logger->api('laundrycard', 'POST', '/api/AuthToken', null, $this->ms($started));
            throw new RetryableException('LaundryCard authentication endpoint unreachable: '.Sanitizer::string($e->getMessage(), 200), null, 'laundrycard.auth');
        }
        $this->logger->api('laundrycard', 'POST', '/api/AuthToken', $response->status(), $this->ms($started));

        if (in_array($response->status(), [400, 401, 403], true)) {
            throw new AuthorizationException('LaundryCard rejected the configured credentials.', $response->status(), 'laundrycard.auth');
        }
        $this->throwForStatus($response, 'laundrycard.auth');

        $token = $response->json('Token') ?? $response->json('token');
        // Some deployments return the bare token as a JSON string.
        if (! $token && is_string($response->json())) {
            $token = $response->json();
        }
        if (! is_string($token) || $token === '') {
            throw new MalformedResponseException('LaundryCard AuthToken response did not contain a Token.', $response->status(), 'laundrycard.auth');
        }

        Cache::put(self::TOKEN_CACHE_KEY, $token, (int) config('integration.laundrycard.token_ttl'));

        return $token;
    }

    /** Returns a sanitized health summary. */
    public function testConnection(): array
    {
        $this->authenticate();
        $stores = $this->stores();

        return ['authenticated' => true, 'store_count' => count($stores), 'store_ids' => array_column($stores, 'store_id')];
    }

    /** @return list<array<string,mixed>> */
    public function stores(): array
    {
        // noStatus=true avoids slow calls out to remote Xchangers; we do not need live status.
        $json = $this->get('/api/stores', ['noStatus' => 'true'], 'laundrycard.stores');
        $rows = $this->listOf($json, 'laundrycard.stores');

        return array_values(array_filter(array_map(fn ($r) => $this->normalizeStore($r), $rows)));
    }

    /** @return list<array<string,mixed>> normalized cards */
    public function cards(string|int $storeId, ?string $minCardNum = null, ?string $maxCardNum = null): array
    {
        $query = array_filter([
            'StoreID' => (int) $storeId,
            'MinCardNum' => $minCardNum,
            'MaxCardNum' => $maxCardNum,
        ], fn ($v) => $v !== null);

        $rows = $this->listOf($this->get('/api/cards', $query, 'laundrycard.cards', ['store_id' => Normalizer::storeId($storeId)]), 'laundrycard.cards');

        $cards = [];
        foreach ($rows as $row) {
            $card = $this->normalizeCard($row, $storeId);
            if ($card) {
                $cards[] = $card;
            }
        }

        return $cards;
    }

    /** Documented POS Account (GET only). Needs LAUNDRYCARD_POS_MACH_NO. Returns null when unavailable. */
    public function account(string|int $storeId, string $cardNumber): ?array
    {
        $mach = config('integration.laundrycard.pos_mach_no');
        if (! filled($mach)) {
            return null;
        }
        $path = '/api/pos/'.(int) $storeId.'/'.rawurlencode((string) $mach).'/Account/'.rawurlencode($cardNumber);
        $json = $this->get($path, [], 'laundrycard.account', ['store_id' => Normalizer::storeId($storeId)], allow404: true);
        if ($json === null) {
            return null;
        }
        if (! is_array($json)) {
            throw new MalformedResponseException('LaundryCard Account response was not an object.', null, 'laundrycard.account');
        }

        return [
            'name' => $this->field($json, 'Name'),
            'email' => Normalizer::email($this->field($json, 'Email')),
            'phone' => $this->field($json, 'Phone'),
            'balance' => $this->normalizeBalance($this->field($json, 'Balance')),
            'balance_present' => $this->hasField($json, 'Balance'),
        ];
    }

    /** @return list<array<string,mixed>> */
    public function transactions(string|int $storeId, ?string $minDateTime = null, ?string $maxDateTime = null): array
    {
        $query = array_filter(['StoreID' => (int) $storeId, 'MinDateTime' => $minDateTime, 'MaxDateTime' => $maxDateTime]);

        return $this->listOf($this->get('/api/transact', $query, 'laundrycard.transactions'), 'laundrycard.transactions');
    }

    // ------------------------------------------------------------------ internals

    private function get(string $path, array $query, string $stage, array $logFields = [], bool $allow404 = false): mixed
    {
        $token = Cache::get(self::TOKEN_CACHE_KEY) ?: $this->authenticate();

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $started = microtime(true);
            try {
                $response = $this->baseRequest()->withToken($token)->get($path, $query);
            } catch (ConnectionException $e) {
                $this->logger->api('laundrycard', 'GET', $this->safePath($path), null, $this->ms($started), $logFields);
                throw new RetryableException('LaundryCard API unreachable or timed out: '.Sanitizer::string($e->getMessage(), 200), null, $stage);
            }
            $this->logger->api('laundrycard', 'GET', $this->safePath($path), $response->status(), $this->ms($started), $logFields);

            if ($response->status() === 401 && $attempt === 0) {
                // Documented behaviour: expired token => 401, re-POST credentials.
                Cache::forget(self::TOKEN_CACHE_KEY);
                $token = $this->authenticate();

                continue;
            }
            if ($response->status() === 404 && $allow404) {
                return null;
            }
            $this->throwForStatus($response, $stage);

            $json = $response->json();
            if ($json === null && trim($response->body()) !== '' && trim($response->body()) !== 'null') {
                throw new MalformedResponseException('LaundryCard returned non-JSON content.', $response->status(), $stage);
            }

            return $json ?? [];
        }

        throw new AuthorizationException('LaundryCard rejected the token after re-authentication.', 401, $stage);
    }

    private function baseRequest(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('integration.laundrycard.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('integration.laundrycard.timeout'));
    }

    private function throwForStatus(Response $response, string $stage): void
    {
        $s = $response->status();
        if ($s < 400) {
            return;
        }
        if ($s === 429) {
            throw new RetryableException('LaundryCard rate limit reached.', 429, $stage, (int) ($response->header('Retry-After') ?: 60));
        }
        if ($s === 401 || $s === 403) {
            throw new AuthorizationException("LaundryCard denied access (HTTP $s).", $s, $stage);
        }
        if ($s >= 500 || $s === 408) {
            throw new RetryableException("LaundryCard server error (HTTP $s).", $s, $stage);
        }
        throw new IntegrationException("LaundryCard request failed (HTTP $s).", $s, $stage);
    }

    private function listOf(mixed $json, string $stage): array
    {
        if (! is_array($json)) {
            throw new MalformedResponseException('Expected a JSON list from LaundryCard.', null, $stage);
        }
        // Tolerate wrapped lists ({"Items":[...]}) but never a scalar.
        if (! array_is_list($json)) {
            foreach (['Items', 'items', 'Data', 'data', 'Stores', 'Cards'] as $k) {
                if (isset($json[$k]) && is_array($json[$k])) {
                    return $json[$k];
                }
            }
            if ($json === []) {
                return [];
            }
            throw new MalformedResponseException('Unexpected LaundryCard response shape.', null, $stage);
        }

        return $json;
    }

    private function normalizeStore(mixed $r): ?array
    {
        if (! is_array($r)) {
            return null;
        }
        $id = $this->field($r, 'ID') ?? $this->field($r, 'StoreID');
        if ($id === null || $id === '') {
            return null;
        }

        return [
            'store_id' => Normalizer::storeId($id),
            'name' => $this->field($r, 'Name'),
            'address' => $this->field($r, 'Address'),
            'city' => $this->field($r, 'City'),
            'state' => $this->field($r, 'State'),
            'zip_code' => $this->field($r, 'ZipCode'),
            'timezone' => $this->field($r, 'TimeZone'),
            'live_enabled' => isset($r['LiveEnabled']) ? (bool) $r['LiveEnabled'] : null,
        ];
    }

    private function normalizeCard(mixed $r, string|int $storeId): ?array
    {
        if (! is_array($r)) {
            return null;
        }
        $number = $this->field($r, 'CardNumber');
        if ($number === null || $number === '') {
            return null;
        }
        $ts = null;
        if ($raw = $this->field($r, 'Timestamp')) {
            try {
                $ts = CarbonImmutable::parse($raw, 'UTC');
            } catch (Throwable) {
                $ts = null;
            }
        }

        return [
            'store_id' => Normalizer::storeId($this->field($r, 'StoreID') ?? $storeId),
            'card_number' => (string) $number,
            'status' => $r['Status'] ?? null,
            'source_timestamp' => $ts,
            'email' => Normalizer::email($this->field($r, 'Email')),
            // CCI documents Balance as the card's dollar balance (decimal).
            'balance' => $this->normalizeBalance($this->field($r, 'Balance')),
            'balance_present' => $this->hasField($r, 'Balance'),
        ];
    }

    private function normalizeBalance(mixed $value): ?string
    {
        if (! is_numeric($value) || ! is_finite((float) $value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function hasField(array $row, string $name): bool
    {
        foreach (array_keys($row) as $key) {
            if (strcasecmp((string) $key, $name) === 0) {
                return true;
            }
        }

        return false;
    }

    private function field(array $row, string $name): mixed
    {
        foreach ($row as $k => $v) {
            if (strcasecmp((string) $k, $name) === 0) {
                return is_string($v) ? trim($v) : $v;
            }
        }

        return null;
    }

    private function safePath(string $path): string
    {
        // Card numbers in POS paths are identifiers for customers; mask them in diagnostics.
        return preg_replace('#/Account/[^/]+#', '/Account/{card}', $path);
    }

    private function ms(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }
}
