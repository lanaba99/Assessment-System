<?php

declare(strict_types=1);

namespace App\Domains\Identity\Exceptions;

class InvalidCredentialsException extends AuthenticationFailedException
{
    public static function forUnknownIdentifier(): self
    {
        return new self('Invalid credentials.', 'invalid_credentials');
    }

    public static function forWrongPassword(): self
    {
        return new self('Invalid credentials.', 'invalid_credentials');
    }
}
