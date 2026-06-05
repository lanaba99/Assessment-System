<?php

declare(strict_types=1);

namespace App\Domains\ExamEngine\DTOs;

final readonly class UpdateExamCommand
{
    public function __construct(
        public ?string $examName = null,
        public ?string $examCode = null,
        public ?string $examDescription = null,
        public ?string $examType = null,
        public ?string $assessmentMode = null,
        public ?int $totalQuestions = null,
        public ?int $totalDurationMinutes = null,
        public ?float $passMarkPercentage = null,
        public ?int $difficultyTierLevel = null,
        public ?bool $isAdaptiveExam = null,
        public ?bool $isRandomized = null,
        public ?bool $allowReviewAfterSubmit = null,
        public ?bool $allowFlaggingForReview = null,
        public ?bool $timerVisibleToCandidate = null,
        public ?bool $showCorrectAnswersAfter = null,
        public ?array $securityProtocols = null,
        public ?array $examMetadata = null,
    ) {
    }
}
