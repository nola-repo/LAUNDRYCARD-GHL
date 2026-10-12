<?php

namespace App\Exceptions\Integration;

/** The remote API answered, but not in the documented shape. */
class MalformedResponseException extends IntegrationException
{
}
