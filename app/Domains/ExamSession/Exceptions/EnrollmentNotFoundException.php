<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\Exceptions;

use RuntimeException;

class EnrollmentNotFoundException extends RuntimeException
{
    public static function forCandidate(string $candidateId, string $examId): self
    {
        return new self(
            "No enrollment found for candidate [{$candidateId}] on exam [{$examId}]."
        );
    }
}
