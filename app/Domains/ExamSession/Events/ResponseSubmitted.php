<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\Events;

use App\Domains\ExamSession\DTOs\SubmitResponseCommand;
use DateTimeImmutable;

final readonly class ResponseSubmitted
{
    public function __construct(
        public SubmitResponseCommand $command,
        public string $questionVersionId,
        public string $sectionId,
        public int $questionSequenceNumber,
        public int $sessionItemVersionLockAfter,
        public int $sessionVersionLockAfter,
        public int $totalQuestionsResponded,
        public int $totalQuestionsFlagged,
        public DateTimeImmutable $submittedAt,
    ) {
    }
}
