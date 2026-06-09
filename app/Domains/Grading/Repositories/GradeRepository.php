<?php

declare(strict_types=1);

namespace App\Domains\Grading\Repositories;

use App\Domains\Grading\DTOs\AssessmentSummary;
use App\Domains\Grading\Exceptions\GradeAlreadyFinalizedException;
use App\Domains\Grading\Models\Grade;

/**
 * Tenant isolation: explicit where('tenant_id') on every query.
 * Grade uses AutoFillsTenantId (no global scope), so this repository is
 * the primary isolation layer for grade data.
 */
class GradeRepository
{
    public function __construct(
        private readonly Grade $model,
    ) {
    }

    public function findBySession(string $tenantId, string $sessionId): ?Grade
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('session_id', $sessionId)
            ->first();
    }

    /**
     * Cross-domain read consumed by the Rules domain's eligibility-chain evaluator.
     * Returns the candidate's highest passing grade for an exam within the same tenant,
     * optionally gated by a minimum normalized score threshold.
     *
     * NOTE: The Rules domain caller must supply tenantId when invoking this method.
     */
    public function findPassingGradeForCandidate(
        string $tenantId,
        string $candidateId,
        string $examId,
        ?float $minScore = null,
    ): ?Grade {
        $query = $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('candidate_user_id', $candidateId)
            ->where('exam_id', $examId)
            ->where('is_passing_grade', true)
            ->where('is_final_grade', true);

        if ($minScore !== null) {
            $query->where('normalized_score', '>=', $minScore);
        }

        return $query->orderByDesc('finalized_at')->first();
    }

    /**
     * Assert that the grade has not yet been finalised.
     *
     * Call this before any write operation on an existing row.
     * The guard is intentionally a no-op when the grade does not yet exist —
     * initial creation is always permitted regardless of the summary's isFinal flag.
     *
     * @throws GradeAlreadyFinalizedException when is_final_grade = true
     */
    public function guardAgainstModification(Grade $grade): void
    {
        if ($grade->is_final_grade) {
            throw GradeAlreadyFinalizedException::forSession((string) $grade->session_id);
        }
    }

    public function upsertFromSummary(AssessmentSummary $summary): Grade
    {
        $now = now();

        // weighted_score: pre-penalty blueprint-weighted equivalent in points.
        // Preserved as a reference score so reviewers can compare the "earned" score
        // against the penalised final score.
        $weightedScorePoints = $summary->weightedScore !== null && $summary->maxScore > 0.0
            ? round($summary->weightedScore / 100.0 * $summary->maxScore, 2)
            : $summary->rawScore;

        // summary->percentage is the POST-PENALTY final score set by
        // AssessmentFinalizationServiceImpl::buildSummary (clamped ≥ 0).
        // normalized_score = pre-penalty weighted % (reference, for eligibility checks).
        // final_score      = post-penalty % (the candidate's official result).
        $prePenaltyPercentage = $summary->weightedScore ?? $summary->percentage + $summary->penaltyDeduction;
        $finalPercentage = $summary->percentage;

        $attributes = [
            'session_id' => $summary->sessionId,
            'candidate_user_id' => $summary->candidateId,
            'exam_id' => $summary->examId,
            'tenant_id' => $summary->tenantId,
            'raw_score' => $summary->rawScore,
            'weighted_score' => $weightedScorePoints,
            'normalized_score' => round($prePenaltyPercentage, 2),
            'final_score' => $finalPercentage,
            'grade_letter' => $summary->gradeLetter,
            'is_passing_grade' => $summary->isPassing,
            'requires_second_marking' => ! $summary->isFinal,
            'is_final_grade' => $summary->isFinal,
            'grading_metadata' => [
                'total_evaluations' => $summary->totalEvaluations,
                'pending_evaluations' => $summary->pendingEvaluations,
                'correct_count' => $summary->correctCount,
                'incorrect_count' => $summary->incorrectCount,
                'max_score' => $summary->maxScore,
                'breakdown' => $summary->breakdown,
                'blueprint_weighted' => $summary->weightedScore !== null,
                // Phase D audit trail — documents exactly why final_score ≠ normalized_score.
                'penalty_deduction' => $summary->penaltyDeduction,
                'sanctions_applied' => $summary->sanctionsApplied,
            ],
            'graded_at' => $now,
            'finalized_at' => $summary->isFinal ? $now : null,
        ];

        // Pass tenantId to the scoped read so the upsert only matches
        // the correct tenant's row even when session UUIDs are non-unique.
        $existing = $this->findBySession($summary->tenantId, $summary->sessionId);

        if ($existing !== null) {
            // Hard immutability guard — throws if is_final_grade = true.
            // The soft guard in AssessmentFinalizationServiceImpl returns early before
            // reaching this point for normal re-finalization calls, but this check
            // protects against any future code path that calls upsertFromSummary directly.
            $this->guardAgainstModification($existing);

            $existing->forceFill($attributes)->save();

            return $existing;
        }

        $attributes['created_at'] = $now;

        return $this->model->newQuery()->create($attributes);
    }
}
