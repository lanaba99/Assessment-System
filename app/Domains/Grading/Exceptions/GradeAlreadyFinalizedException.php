<?php

declare(strict_types=1);

namespace App\Domains\Grading\Exceptions;

use RuntimeException;

class GradeAlreadyFinalizedException extends RuntimeException
{
    public static function forSession(string $sessionId): self
    {
        return new self(
            "Grade for session [{$sessionId}] is already finalized (is_final_grade = true) and cannot be modified. "
            . 'Re-finalization is only permitted while the grade remains provisional.'
        );
    }
}
