<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\Exceptions;

use RuntimeException;

class SessionItemNotFoundException extends RuntimeException
{
    public static function forSessionItemAndSession(string $sessionItemId, string $sessionId): self
    {
        return new self("Session item [{$sessionItemId}] not found on session [{$sessionId}].");
    }
}