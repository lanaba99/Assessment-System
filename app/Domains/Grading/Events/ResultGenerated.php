<?php

declare(strict_types=1);

namespace App\Domains\Grading\Events;

use App\Domains\Grading\DTOs\AssessmentSummary;
use DateTimeImmutable;

final readonly class ResultGenerated
{
    public function __construct(
        public AssessmentSummary $summary,
        public bool $isFirstFinalization,
        public DateTimeImmutable $calculatedAt,
    ) {
    }
}
