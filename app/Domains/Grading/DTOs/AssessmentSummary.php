<?php

declare(strict_types=1);

namespace App\Domains\Grading\DTOs;

final readonly class AssessmentSummary
{
    public const STATUS_PROVISIONAL = 'provisional';
    public const STATUS_FINAL = 'final';

    public function __construct(
        public string $sessionId,
        public string $candidateId,
        public string $examId,
        public string $tenantId,
        public float $rawScore,
        public float $maxScore,
        public float $percentage,
        public string $gradeLetter,
        public bool $isPassing,
        public bool $isFinal,
        public int $totalEvaluations,
        public int $pendingEvaluations,
        public int $correctCount,
        public int $incorrectCount,
        public array $breakdown = [],
    ) {
    }

    public function resultStatus(): string
    {
        return $this->isFinal ? self::STATUS_FINAL : self::STATUS_PROVISIONAL;
    }
}
