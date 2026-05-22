<?php

declare(strict_types=1);

namespace App\Domains\Identity\Exceptions;

use RuntimeException;

/**
 * Base for all authentication failures. Callers (HTTP layer) typically map this to 401.
 */
abstract class AuthenticationFailedException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reasonCode)
    {
        parent::__construct($message);
    }
}
