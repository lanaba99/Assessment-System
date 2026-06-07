<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\Exceptions;

use RuntimeException;

class EnrollmentAlreadyExistsException extends RuntimeException
{
    public static function forCandidateAndExam(string $candidateUserId, string $examId): self
    {
        return new self(
            "Candidate [{$candidateUserId}] is already enrolled in exam [{$examId}]."
        );
    }
}
