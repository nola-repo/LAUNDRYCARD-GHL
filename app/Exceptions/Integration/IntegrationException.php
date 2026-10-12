<?php

namespace App\Exceptions\Integration;

use RuntimeException;

/** Base class. Messages must already be safe to persist. */
class IntegrationException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $httpStatus = null, public readonly string $stage = 'unknown')
    {
        parent::__construct($message);
    }
}
