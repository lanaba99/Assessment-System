<?php

declare(strict_types=1);

namespace App\Domains\Grading\DTOs;

final readonly class GradingResult
{
    public const EVAL_TYPE_AUTO = 'auto';
    public const EVAL_TYPE_MANUAL_PENDING = 'manual_pending';
    // Set by ManualEvaluationServiceImpl after a human evaluator submits a score.
    public const EVAL_TYPE_MANUAL = 'manual';

    public const STATUS_SCORED = 'scored';
    public const STATUS_PENDING_REVIEW = 'pending_review';

    public function __construct(
        public string $sessionId,
        public string $sessionItemId,
        public string $questionId,
        public string $questionVersionId,
        public ?bool $isCorrect,
        public float $rawScore,
        public float $maxScore,
        public float $normalizedScore,
        public string $evaluationType,
        public string $evaluationStatus,
        public bool $requiresSecondaryReview = false,
        public array $evaluationMetadata = [],
    ) {
    }

    public function isAutoScored(): bool
    {
        return $this->evaluationType === self::EVAL_TYPE_AUTO;
    }
}
