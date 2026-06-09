<?php

declare(strict_types=1);

namespace App\Domains\Grading\Services;

use App\Domains\ExamEngine\Repositories\ExamBlueprintRepository;
use App\Domains\ExamSession\Events\ExamSessionCompleted;
use App\Domains\ExamSession\Repositories\ExamSessionItemRepository;
use App\Domains\Grading\Contracts\AssessmentFinalizationService;
use App\Domains\Grading\DTOs\AssessmentSummary;
use App\Domains\Grading\DTOs\PenaltyDeductionResult;
use App\Domains\Grading\DTOs\WeightedScoreResult;
use App\Domains\Grading\Events\ResultGenerated;
use App\Domains\Grading\Models\AnswerEvaluation;
use App\Domains\Grading\Repositories\AnswerEvaluationRepository;
use App\Domains\Grading\Repositories\AssessmentResultRepository;
use App\Domains\Grading\Repositories\GradeRepository;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AssessmentFinalizationServiceImpl implements AssessmentFinalizationService
{
    private const PASS_THRESHOLD_PERCENT = 60.0;

    private const PENDING_STATUS = 'pending_review';

    public function __construct(
        private readonly AnswerEvaluationRepository $evaluations,
        private readonly GradeRepository $grades,
        private readonly AssessmentResultRepository $results,
        private readonly WeightedScoringService $weightedScoring,
        private readonly ExamBlueprintRepository $blueprintRepository,
        private readonly ExamSessionItemRepository $itemRepository,
        private readonly CompetencyScoringService $competencyScoring,
        private readonly PenaltyApplicationService $penaltyService,
    ) {
    }

    public function finalize(ExamSessionCompleted $event): AssessmentSummary
    {
        // ── Soft immutability guard (pre-transaction) ─────────────────────────
        //
        // If the session already has a finalised grade (is_final_grade = true),
        // skip the entire recalculation pipeline and return the cached result.
        //
        // This check is the "soft" layer. The hard layer lives in
        // GradeRepository::guardAgainstModification, which throws
        // GradeAlreadyFinalizedException if upsertFromSummary is ever called on
        // a finalised row by any future code path that bypasses this early return.
        $existingGrade = $this->grades->findBySession($event->tenantId, $event->sessionId);
        if ($existingGrade !== null && $existingGrade->is_final_grade) {
            return $this->summaryFromGrade($existingGrade);
        }

        /** @var array{summary: AssessmentSummary, shouldFire: bool} $outcome */
        $outcome = DB::transaction(function () use ($event): array {
            // Load all data required for the grading pipeline.
            $evaluations = $this->evaluations->findBySession($event->tenantId, $event->sessionId);

            // blueprints: no tenant column — isolation is via exam_id FK (exam is
            // already tenant-scoped; examId comes from a validated ExamSessionCompleted).
            $blueprints = $this->blueprintRepository->findSectionedForExam($event->examId);

            // session items: no tenant_id — scoped by session_id which is tenant-owned.
            $sessionItems = $this->itemRepository->findBySession($event->sessionId);

            // Compute blueprint-weighted score (pure domain logic, no DB writes).
            $weightedResult = $this->weightedScoring->compute($evaluations, $blueprints, $sessionItems);

            // Compute penalty deductions (read-only: never writes to penalty tables).
            // A single repository read returns both the total and the per-sanction
            // audit trail so we do not hit the DB twice.
            $penaltyResult = $this->penaltyService->computeWithAudit($event->tenantId, $event->sessionId);

            // buildSummary applies the penalty clamp and produces the final,
            // immutable AssessmentSummary. No code path outside AssessmentFinalizationService
            // can overwrite final_score because only this service calls GradeRepository::upsertFromSummary.
            $summary = $this->buildSummary($evaluations, $weightedResult, $event, $penaltyResult);

            // Check finalization state BEFORE upserts so we can fire ResultGenerated
            // only on the true first finalization.
            $existing = $this->results->findBySession($event->tenantId, $event->sessionId);
            $wasFinalBefore = $existing?->result_status === AssessmentSummary::STATUS_FINAL;

            $this->grades->upsertFromSummary($summary);
            $this->results->upsertFromSummary($summary);

            // Persist per-competency scores when blueprints were matched.
            // Skipped when there are no blueprints (e.g. unstructured exams) to
            // avoid writing empty competency_score rows.
            if ($weightedResult->hasBlueprints()) {
                $this->competencyScoring->computeAndPersist(
                    $event->tenantId,
                    $event->sessionId,
                    $event->candidateId,
                    $weightedResult,
                );
            }

            $shouldFire = $summary->isFinal && ! $wasFinalBefore;

            return [
                'summary' => $summary,
                'shouldFire' => $shouldFire,
            ];
        });

        if ($outcome['shouldFire']) {
            event(new ResultGenerated(
                summary: $outcome['summary'],
                isFirstFinalization: true,
                calculatedAt: new DateTimeImmutable(),
            ));
        }

        return $outcome['summary'];
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Builds the AssessmentSummary from raw evaluation data, the weighted score,
     * and the penalty deduction.
     *
     * Score semantics in the resulting summary:
     *   percentage    = final post-penalty score (0-100, clamped ≥ 0)
     *   weightedScore = pre-penalty blueprint-weighted percentage (null if no blueprints)
     *   penaltyDeduction = total points deducted
     *
     * All pass/fail and letter-grade decisions are made on the FINAL (post-penalty)
     * percentage so the candidate's official result correctly reflects any sanctions.
     *
     * This is the only place where final_score is calculated. AssessmentFinalizationService
     * is the sole caller of GradeRepository::upsertFromSummary, enforcing immutability.
     *
     * @param  Collection<int, AnswerEvaluation>  $evaluations
     */
    private function buildSummary(
        Collection $evaluations,
        WeightedScoreResult $weightedResult,
        ExamSessionCompleted $event,
        PenaltyDeductionResult $penaltyResult,
    ): AssessmentSummary {
        $pending = 0;
        $correct = 0;
        $incorrect = 0;
        $breakdown = [];

        foreach ($evaluations as $eval) {
            if ($eval->evaluation_status === self::PENDING_STATUS) {
                $pending++;
            }

            $metadata = is_array($eval->evaluation_metadata) ? $eval->evaluation_metadata : [];
            $isCorrect = $metadata['is_correct'] ?? null;

            if ($isCorrect === true) {
                $correct++;
            } elseif ($isCorrect === false) {
                $incorrect++;
            }

            $breakdown[] = [
                'question_id' => $eval->question_id,
                'score_awarded' => (float) $eval->score_awarded,
                'max_score_possible' => (float) $eval->max_score_possible,
                'evaluation_status' => $eval->evaluation_status,
                'evaluation_type' => $eval->evaluation_type,
                'is_correct' => $isCorrect,
            ];
        }

        $total = $evaluations->count();
        $isFinal = $pending === 0 && $total > 0;

        // Pre-penalty effective percentage (weighted when blueprints matched, flat otherwise).
        $flatPercentage = $weightedResult->rawMaxScore > 0.0
            ? round(($weightedResult->rawScore / $weightedResult->rawMaxScore) * 100.0, 2)
            : 0.0;

        $prePenaltyPercentage = $weightedResult->hasBlueprints()
            ? $weightedResult->weightedPercentage
            : $flatPercentage;

        // Phase D: apply penalty clamp.
        // final = max(0, pre-penalty − total_deduction) — never goes negative.
        $finalPercentage = round(
            max(0.0, $prePenaltyPercentage - $penaltyResult->totalDeduction),
            2,
        );

        $hasScorable = $weightedResult->rawMaxScore > 0.0;

        return new AssessmentSummary(
            sessionId: $event->sessionId,
            candidateId: $event->candidateId,
            examId: $event->examId,
            tenantId: $event->tenantId,
            rawScore: $weightedResult->rawScore,
            maxScore: $weightedResult->rawMaxScore,
            // percentage = FINAL post-penalty score — this is what drives pass/fail
            // and what GradeRepository writes into normalized_score and final_score.
            percentage: $finalPercentage,
            gradeLetter: $this->letterFor($finalPercentage, $hasScorable),
            isPassing: $finalPercentage >= self::PASS_THRESHOLD_PERCENT && $hasScorable,
            isFinal: $isFinal,
            totalEvaluations: $total,
            pendingEvaluations: $pending,
            correctCount: $correct,
            incorrectCount: $incorrect,
            breakdown: $breakdown,
            // Pre-penalty weighted percentage for Grade.weighted_score (reference field).
            // Null when no blueprints matched so GradeRepository falls back to rawScore.
            weightedScore: $weightedResult->hasBlueprints() ? $prePenaltyPercentage : null,
            penaltyDeduction: $penaltyResult->totalDeduction,
            sanctionsApplied: $penaltyResult->sanctionsApplied,
        );
    }

    /**
     * Reconstruct an AssessmentSummary from a stored Grade row.
     *
     * Used by the immutability early return when finalize() is called on a session
     * whose grade is already final. All score fields, audit trail data, and flags are
     * read back from the Grade columns and grading_metadata JSON so the returned
     * summary is identical to what the original finalization produced.
     */
    private function summaryFromGrade(Grade $grade): AssessmentSummary
    {
        $metadata = is_array($grade->grading_metadata) ? $grade->grading_metadata : [];

        // normalized_score = pre-penalty blueprint-weighted %
        // final_score      = post-penalty official result
        $isBlueprinted = (bool) ($metadata['blueprint_weighted'] ?? false);

        return new AssessmentSummary(
            sessionId: (string) $grade->session_id,
            candidateId: (string) $grade->candidate_user_id,
            examId: (string) $grade->exam_id,
            tenantId: (string) $grade->tenant_id,
            rawScore: (float) $grade->raw_score,
            maxScore: (float) ($metadata['max_score'] ?? 0.0),
            percentage: (float) $grade->final_score,
            gradeLetter: (string) ($grade->grade_letter ?? 'N/A'),
            isPassing: (bool) $grade->is_passing_grade,
            isFinal: (bool) $grade->is_final_grade,
            totalEvaluations: (int) ($metadata['total_evaluations'] ?? 0),
            pendingEvaluations: (int) ($metadata['pending_evaluations'] ?? 0),
            correctCount: (int) ($metadata['correct_count'] ?? 0),
            incorrectCount: (int) ($metadata['incorrect_count'] ?? 0),
            breakdown: is_array($metadata['breakdown'] ?? null) ? $metadata['breakdown'] : [],
            weightedScore: $isBlueprinted ? (float) $grade->normalized_score : null,
            penaltyDeduction: (float) ($metadata['penalty_deduction'] ?? 0.0),
            sanctionsApplied: is_array($metadata['sanctions_applied'] ?? null)
                ? $metadata['sanctions_applied']
                : [],
        );
    }

    private function letterFor(float $percentage, bool $hasScorableEvaluations): string
    {
        if (! $hasScorableEvaluations) {
            return 'N/A';
        }

        return match (true) {
            $percentage >= 90.0 => 'A',
            $percentage >= 80.0 => 'B',
            $percentage >= 70.0 => 'C',
            $percentage >= 60.0 => 'D',
            default => 'F',
        };
    }
}
