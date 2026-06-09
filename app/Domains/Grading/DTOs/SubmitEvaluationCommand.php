<?php

declare(strict_types=1);

namespace App\Domains\Grading\DTOs;

final readonly class SubmitEvaluationCommand
{
    public function __construct(
        public string $tenantId,
        public string $evaluationId,
        public string $evaluatorUserId,
        public float $scoreAwarded,
        public ?string $rubricId = null,
        public ?array $rubricCriteriaJson = null,
        public ?array $evaluatorComments = null,
        public bool $requiresSecondaryReview = false,
    ) {
    }
}
