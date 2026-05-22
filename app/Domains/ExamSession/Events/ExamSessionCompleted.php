<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\Events;

use DateTimeImmutable;

final readonly class ExamSessionCompleted
{
    public function __construct(
        public string $sessionId,
        public string $tenantId,
        public string $candidateId,
        public string $examId,
        public string $finalState,
        public string $completionMethod,
        public DateTimeImmutable $endedAt,
        public int $totalQuestionsResponded,
        public int $totalQuestionsFlagged,
        public int $versionLockAfter,
    ) {
    }
}
