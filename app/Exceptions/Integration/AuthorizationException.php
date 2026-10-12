<?php

namespace App\Exceptions\Integration;

/** Credentials rejected / revoked. Not retryable until an administrator reconnects. */
class AuthorizationException extends IntegrationException
{
}
