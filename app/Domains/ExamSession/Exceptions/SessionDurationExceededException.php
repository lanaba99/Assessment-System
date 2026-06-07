<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\Exceptions;

use RuntimeException;

class SessionDurationExceededException extends RuntimeException
{
    public static function forSession(string $sessionId, int $allowedMinutes): self
    {
        return new self(
            "Session [{$sessionId}] has exceeded the allowed duration of {$allowedMinutes} minute(s). No further responses can be recorded."
        );
    }
}
