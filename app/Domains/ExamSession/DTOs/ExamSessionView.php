<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\DTOs;

use DateTimeImmutable;

final readonly class ExamSessionView
{
    public function __construct(
        public string $sessionId,
        public string $tenantId,
        public string $examId,
        public string $candidateId,
        public string $enrollmentId,
        public string $state,
        public ?string $currentSessionItemId,
        public ?string $currentQuestionVersionId,
        public ?string $currentSectionId,
        public int $currentQuestionIndex,
        public int $totalQuestionsResponded,
        public int $totalQuestionsFlagged,
        public ?DateTimeImmutable $sessionStartedAt,
        public ?DateTimeImmutable $sessionResumedAt,
        public ?DateTimeImmutable $sessionEndedAt,
        public ?int $totalSessionDurationSeconds,
        public ?DateTimeImmutable $lastHeartbeatAt,
        public int $versionLock,
        public array $progressJson = [],
    ) {
    }
}
