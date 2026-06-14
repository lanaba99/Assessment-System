<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\Exceptions;

use RuntimeException;

class EligibilityViolationException extends RuntimeException
{
    public static function examNotPublished(string $examId): self
    {
        return new self("Exam [{$examId}] is not published and cannot be started.");
    }

    public static function inactiveEnrollment(string $enrollmentId): self
    {
        return new self("Enrollment [{$enrollmentId}] is not active.");
    }

    public static function windowNotOpen(string $enrollmentId): self
    {
        return new self(
            "Enrollment [{$enrollmentId}] is outside its allowed scheduling window."
        );
    }

    public static function attemptsExhausted(string $enrollmentId): self
    {
        return new self(
            "Enrollment [{$enrollmentId}] has no remaining attempts."
        );
    }

    public static function notCohortMember(string $candidateId, string $cohortId): self
    {
        return new self(
            "Candidate [{$candidateId}] is not an active member of cohort [{$cohortId}]."
        );
    }

    public static function prerequisiteChainFailed(string $reason): self
    {
        return new self($reason);
    }
}
