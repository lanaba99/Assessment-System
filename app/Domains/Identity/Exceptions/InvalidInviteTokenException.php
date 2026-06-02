<?php

declare(strict_types=1);

namespace App\Domains\Identity\Exceptions;

use RuntimeException;

class InvalidInviteTokenException extends RuntimeException
{
    public static function invalidOrExpired(): self
    {
        return new self('The invitation token is invalid or has expired.');
    }

    public static function userNotPending(): self
    {
        return new self('This account is not eligible for invite acceptance.');
    }
}
