<?php

namespace App\Services;

use App\Exceptions\Integration\AuthorizationException;
use App\Exceptions\Integration\IntegrationException;
use App\Exceptions\Integration\MalformedResponseException;
use App\Exceptions\Integration\RetryableException;
use App\Models\GhlConnection;
use App\Support\Sanitizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * HighLevel API v2 client. Every call is made with the OAuth token of ONE
 * explicit connection, so a request can never be sent to another location.
 */
class HighLevelClient
{
    public function __construct(private HighLevelOAuthService $oauth, private IntegrationLogger $logger)
    {
    }

    /** Lightweight authenticated read used by "Test connection". */
    public function testConnection(GhlConnection $connection): array
    {
        $response = $this->send($connection, 'GET', '/locations/'.rawurlencode($connection->location_id), [], 'ghl.test', '/locations/{id}');
        $loc = $response->json('location') ?? $response->json();
        if (is_array($loc) && ! empty($loc['name'])) {
            $connection->update(['location_name' => $loc['name']]);
        }

        return ['ok' => true, 'location_name' => $loc['name'] ?? null, 'http_status' => $response->status()];
    }

    /**
     * Documented "Get Duplicate Contact": GET /contacts/search/duplicate?locationId=&email=&number=
     * Returns the contact array or null.
     */
    public function findDuplicate(GhlConnection $connection, ?string $email, ?string $phone): ?array
    {
        $query = array_filter(['locationId' => $connection->location_id, 'email' => $email, 'number' => $phone]);
        if (! $email && ! $phone) {
            return null;
        }
        $response = $this->send($connection, 'GET', '/contacts/search/duplicate', ['query' => $query], 'ghl.contact.lookup', '/contacts/search/duplicate', allow404: true);
        if ($response->status() === 404) {
            return null;
        }
        $contact = $response->json('contact');

        return is_array($contact) && ! empty($contact['id']) ? $contact : null;
    }

    public function createContact(GhlConnection $connection, array $payload): array
    {
        $payload['locationId'] = $connection->location_id; // always the connection's own location
        $response = $this->send($connection, 'POST', '/contacts', ['json' => $payload], 'ghl.contact.create', '/contacts');
        $contact = $response->json('contact');
        if (! is_array($contact) || empty($contact['id'])) {
            throw new MalformedResponseException('GHL create-contact response had no contact id.', $response->status(), 'ghl.contact.create');
        }

        return $contact;
    }

    public function updateContact(GhlConnection $connection, string $contactId, array $payload): array
    {
        unset($payload['locationId']);
        $response = $this->send($connection, 'PUT', '/contacts/'.rawurlencode($contactId), ['json' => $payload], 'ghl.contact.update', '/contacts/{id}');
        $contact = $response->json('contact');

        return is_array($contact) ? $contact : ['id' => $contactId];
    }

    public function getContact(GhlConnection $connection, string $contactId): ?array
    {
        $response = $this->send($connection, 'GET', '/contacts/'.rawurlencode($contactId), [], 'ghl.contact.get', '/contacts/{id}', allow404: true);
        if ($response->status() === 404) {
            return null;
        }
        $contact = $response->json('contact');

        return is_array($contact) ? $contact : null;
    }

    // ------------------------------------------------------------------ internals

    /** @param array{query?:array,json?:array} $opts */
    private function send(GhlConnection $connection, string $method, string $path, array $opts, string $stage, string $logPath, bool $allow404 = false): Response
    {
        $connection = $this->oauth->ensureFresh($connection);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $started = microtime(true);
            try {
                $req = Http::baseUrl(config('integration.ghl.api_base'))
                    ->acceptJson()->asJson()
                    ->withToken($connection->access_token)
                    ->withHeaders(['Version' => config('integration.ghl.api_version')])
                    ->timeout((int) config('integration.ghl.timeout'));
                $response = $req->send($method, $path, array_filter(['query' => $opts['query'] ?? null, 'json' => $opts['json'] ?? null]));
            } catch (ConnectionException $e) {
                $this->diag($connection, $method, $logPath, null, $started);
                $this->recordError($connection, 'timeout/unreachable');
                throw new RetryableException('GHL API unreachable or timed out.', null, $stage);
            }
            $this->diag($connection, $method, $logPath, $response->status(), $started);

            $s = $response->status();
            if ($s === 401 && $attempt === 0) {
                // Token might have been invalidated early: force a refresh and retry once.
                $connection->update(['expires_at' => now()->subSecond()]);
                $connection = $this->oauth->refresh($connection);

                continue;
            }
            if ($s === 401) {
                $this->oauth->markUnusable($connection, GhlConnection::REVOKED, 'GHL rejected the refreshed token (HTTP 401).');
                throw new AuthorizationException('GHL rejected credentials (401); reconnect required.', 401, $stage);
            }
            if ($s === 403) {
                $this->recordError($connection, 'forbidden (missing scope or access)');
                throw new AuthorizationException('GHL denied the request (403): check granted scopes.', 403, $stage);
            }
            if ($s === 404 && $allow404) {
                return $response;
            }
            if ($s === 429) {
                throw new RetryableException('GHL rate limit reached.', 429, $stage, (int) ($response->header('Retry-After') ?: 60));
            }
            if ($s >= 500 || $s === 408) {
                $this->recordError($connection, "server error $s");
                throw new RetryableException("GHL server error (HTTP $s).", $s, $stage);
            }
            if ($s >= 400) {
                $detail = Sanitizer::string((string) ($response->json('message') ?? 'request rejected'), 150);
                $this->recordError($connection, "HTTP $s $detail");
                throw new IntegrationException("GHL rejected request (HTTP $s): $detail", $s, $stage);
            }

            $connection->update(['last_success_at' => now()]);

            return $response;
        }

        throw new AuthorizationException('GHL authorization failed.', 401, $stage);
    }

    private function recordError(GhlConnection $c, string $msg): void
    {
        $c->update(['last_error' => Sanitizer::string($msg, 200), 'last_error_at' => now()]);
    }

    private function diag(GhlConnection $c, string $method, string $path, ?int $status, float $started): void
    {
        $this->logger->api('ghl', $method, $path, $status, (int) ((microtime(true) - $started) * 1000), ['ghl_location_id' => $c->location_id]);
    }
}
