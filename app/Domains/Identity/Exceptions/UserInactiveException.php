<?php

declare(strict_types=1);

namespace App\Domains\Identity\Exceptions;

class UserInactiveException extends AuthenticationFailedException
{
    public static function forUser(string $userId): self
    {
        return new self("User account is not active.", 'user_inactive');
    }
}
