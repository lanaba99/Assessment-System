<?php

declare(strict_types=1);

namespace App\Domains\Grading\Exceptions;

use RuntimeException;

class ResultNotFinalizedException extends RuntimeException
{
    public static function forSession(string $sessionId): self
    {
        return new self("Assessment result for session [{$sessionId}] cannot be published until its grade and result are final.");
    }
}
