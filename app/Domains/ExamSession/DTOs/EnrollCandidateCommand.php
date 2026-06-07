<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\DTOs;

final readonly class EnrollCandidateCommand
{
    public function __construct(
        public string $tenantId,
        public string $examId,
        public string $candidateUserId,
        public ?string $cohortId = null,
        public ?string $startWindowDate = null,
        public ?string $endWindowDate = null,
        public int $maxAttemptsAllowed = 1,
        public ?string $enrollmentNotes = null,
    ) {
    }
}
