<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\Exceptions;

use RuntimeException;

class StaleVersionLockException extends RuntimeException
{
    public static function forSession(string $sessionId, int $expectedVersion): self
    {
        return new self(
            "Optimistic lock conflict: session '{$sessionId}' was modified by another process (expected version_lock {$expectedVersion})."
        );
    }

    public static function forSessionItem(string $sessionItemId, int $expectedVersion): self
    {
        return new self(
            "Optimistic lock conflict: session item '{$sessionItemId}' was modified by another process (expected version_lock {$expectedVersion})."
        );
    }
}
