<?php

namespace App\Services;

use App\Models\IntegrationEvent;
use App\Support\Sanitizer;
use Illuminate\Support\Str;
use Throwable;

/**
 * Structured, sanitized logging into integration_events.
 * One correlation id per logical operation (scan, sync of one record, OAuth callback).
 */
class IntegrationLogger
{
    private string $correlationId;

    public function __construct()
    {
        $this->correlationId = (string) Str::uuid();
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    public function newCorrelation(?string $id = null): string
    {
        return $this->correlationId = $id ?: (string) Str::uuid();
    }

    /**
     * @param array{level?:string,outcome?:string,store_id?:string,source_record_id?:int,ghl_location_id?:string,http_status?:int,retry_count?:int,message?:string,context?:array} $fields
     */
    public function log(string $stage, array $fields = []): IntegrationEvent
    {
        try {
            return IntegrationEvent::create([
                'correlation_id' => $fields['correlation_id'] ?? $this->correlationId,
                'stage' => $stage,
                'level' => $fields['level'] ?? 'info',
                'outcome' => $fields['outcome'] ?? null,
                'store_id' => $fields['store_id'] ?? null,
                'source_record_id' => $fields['source_record_id'] ?? null,
                'ghl_location_id' => $fields['ghl_location_id'] ?? null,
                'http_status' => $fields['http_status'] ?? null,
                'retry_count' => $fields['retry_count'] ?? 0,
                'message' => Sanitizer::string($fields['message'] ?? null, 1000),
                'context' => isset($fields['context']) ? Sanitizer::context($fields['context']) : null,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            // Logging must never break the pipeline.
            logger()->error('IntegrationLogger failed: '.Sanitizer::string($e->getMessage()));

            return new IntegrationEvent();
        }
    }

    public function error(string $stage, Throwable|string $error, array $fields = []): IntegrationEvent
    {
        $msg = $error instanceof Throwable ? get_class($error).': '.$error->getMessage() : $error;

        return $this->log($stage, ['level' => 'error', 'outcome' => 'failure', 'message' => $msg] + $fields);
    }

    /** Sanitized API diagnostics (method, path, status, duration, response shape only). */
    public function api(string $system, string $method, string $path, ?int $status, int $ms, array $fields = []): void
    {
        $this->log("api.$system", [
            'outcome' => $status && $status < 400 ? 'success' : 'failure',
            'level' => $status && $status < 400 ? 'info' : 'warning',
            'http_status' => $status,
            'message' => strtoupper($method).' '.$path,
            'context' => ['duration_ms' => $ms] + ($fields['context'] ?? []),
        ] + array_diff_key($fields, ['context' => 1]));
    }
}
