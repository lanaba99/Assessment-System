<?php

declare(strict_types=1);

namespace App\Domains\ExamEngine\DTOs;

use DateTimeImmutable;

final readonly class ExamView
{
    public function __construct(
        public string $examId,
        public string $tenantId,
        public string $createdByUserId,
        public string $examName,
        public string $examCode,
        public ?string $examDescription,
        public string $examType,
        public ?string $assessmentMode,
        public int $totalQuestions,
        public int $totalDurationMinutes,
        public float $passMarkPercentage,
        public ?int $difficultyTierLevel,
        public bool $isAdaptiveExam,
        public bool $isRandomized,
        public bool $allowReviewAfterSubmit,
        public bool $allowFlaggingForReview,
        public bool $timerVisibleToCandidate,
        public bool $showCorrectAnswersAfter,
        public ?array $securityProtocols,
        public ?array $examMetadata,
        public string $examStatus,
        public bool $isPublished,
        public ?DateTimeImmutable $publishedAt,
        public ?DateTimeImmutable $archivedAt,
    ) {
    }
}
