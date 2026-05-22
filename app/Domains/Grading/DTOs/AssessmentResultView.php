<?php

declare(strict_types=1);

namespace App\Domains\Grading\DTOs;

use DateTimeImmutable;

final readonly class AssessmentResultView
{
    public function __construct(
        public string $resultId,
        public string $sessionId,
        public string $candidateId,
        public string $examId,
        public string $tenantId,
        public string $resultStatus,
        public string $publicationStatus,
        public ?DateTimeImmutable $resultCalculatedAt,
        public ?DateTimeImmutable $publishedAt,
        public ?AssessmentSummary $summary,
        public array $resultMetadata = [],
    ) {
    }
}
