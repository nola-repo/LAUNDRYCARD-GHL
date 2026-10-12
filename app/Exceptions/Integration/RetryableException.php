<?php

namespace App\Exceptions\Integration;

/** Transient failure (timeout, 5xx, rate limit). Safe to retry. */
class RetryableException extends IntegrationException
{
    public function __construct(string $message, ?int $httpStatus = null, string $stage = 'unknown', public readonly ?int $retryAfter = null)
    {
        parent::__construct($message, $httpStatus, $stage);
    }
}
