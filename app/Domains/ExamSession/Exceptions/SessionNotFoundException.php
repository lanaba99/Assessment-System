<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\Exceptions;

use RuntimeException;

class SessionNotFoundException extends RuntimeException
{
    public static function forId(string $sessionId): self
    {
        return new self("Exam session [{$sessionId}] not found.");
    }
}
