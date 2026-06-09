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

        // Blueprint-weighted percentage (0-100). Set by AssessmentFinalizationServiceImpl
        // when exam blueprints are present; null when falling back to flat scoring.
        // GradeRepository uses this to populate Grade.weighted_score so it reflects
        // the pre-penalty, blueprint-weighted score for reference.
        public ?float $weightedScore = null,

        // Total percentage-point deduction applied from 'penalty' type sanctions.
        // 0.0 when no deductible sanctions exist for the session.
        // Grade.final_score = max(0, percentage - penaltyDeduction).
        // GradeRepository writes this into grading_metadata for the audit trail.
        public float $penaltyDeduction = 0.0,

        // Per-sanction audit entries — written verbatim into Grade.grading_metadata
        // so reviewers can trace exactly which rules caused the deduction.
        // @var array<int, array{sanction_id: string, penalty_rule_id: string, amount: float, reason: string, applied_at: string|null}>
        public array $sanctionsApplied = [],
    ) {
    }

    public function resultStatus(): string
    {
        return $this->isFinal ? self::STATUS_FINAL : self::STATUS_PROVISIONAL;
    }
}
