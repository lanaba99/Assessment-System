<?php

declare(strict_types=1);

namespace App\Domains\Grading\Exceptions;

use RuntimeException;

class AssessmentResultNotFoundException extends RuntimeException
{
    public static function forSession(string $sessionId): self
    {
        return new self("Assessment result for session [{$sessionId}] was not found.");
    }
}
