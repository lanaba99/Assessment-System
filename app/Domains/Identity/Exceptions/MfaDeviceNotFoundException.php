<?php

declare(strict_types=1);

namespace App\Domains\Identity\Exceptions;

use RuntimeException;

class MfaDeviceNotFoundException extends RuntimeException
{
    public static function forUser(string $userId, string $deviceId): self
    {
        return new self("MFA device {$deviceId} not found for user {$userId}.");
    }
}
