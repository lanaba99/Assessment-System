<?php

declare(strict_types=1);

namespace App\Domains\ExamEngine\DTOs;

final readonly class CreateExamCommand
{
    public function __construct(
        public string $tenantId,
        public string $createdByUserId,
        public string $examName,
        public string $examCode,
        public string $examType,
        public int $totalQuestions,
        public int $totalDurationMinutes,
        public ?string $examDescription = null,
        public ?string $assessmentMode = null,
        public float $passMarkPercentage = 60.0,
        public ?int $difficultyTierLevel = null,
        public bool $isAdaptiveExam = false,
        public bool $isRandomized = false,
        public bool $allowReviewAfterSubmit = false,
        public bool $allowFlaggingForReview = true,
        public bool $timerVisibleToCandidate = true,
        public bool $showCorrectAnswersAfter = false,
        public ?array $securityProtocols = null,
        public ?array $examMetadata = null,
    ) {
    }
}
