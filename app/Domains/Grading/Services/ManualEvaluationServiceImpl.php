<?php

declare(strict_types=1);

namespace App\Domains\Grading\Services;

use App\Domains\ExamSession\Events\ExamSessionCompleted;
use App\Domains\ExamSession\Repositories\SessionRepository;
use App\Domains\Grading\Contracts\AssessmentFinalizationService;
use App\Domains\Grading\Contracts\ManualEvaluationService;
use App\Domains\Grading\DTOs\GradingResult;
use App\Domains\Grading\DTOs\SubmitEvaluationCommand;
use App\Domains\Grading\Exceptions\EvaluationNotFoundException;
use App\Domains\Grading\Exceptions\InvalidEvaluationStateException;
use App\Domains\Grading\Exceptions\InvalidScoreRangeException;
use App\Domains\Grading\Models\AnswerEvaluation;
use App\Domains\Grading\Repositories\AnswerEvaluationRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ManualEvaluationServiceImpl implements ManualEvaluationService
{
    public function __construct(
        private readonly AnswerEvaluationRepository $evaluations,
        private readonly AssessmentFinalizationService $finalization,
        private readonly SessionRepository $sessions,
    ) {
    }

    public function submitScore(SubmitEvaluationCommand $command): AnswerEvaluation
    {
        // ── Guard: evaluation must exist for this tenant ──────────────────────
        $eval = $this->evaluations->findById($command->tenantId, $command->evaluationId)
            ?? throw EvaluationNotFoundException::forId($command->evaluationId);

        // ── Guard: only pending_review items can receive a manual score ───────
        //
        // This check must mirror ManualReviewStrategy: that strategy marks items
        // as EVAL_TYPE_MANUAL_PENDING / STATUS_PENDING_REVIEW. Attempting to
        // re-score an auto-graded or already-scored item is rejected here.
        if ($eval->evaluation_status !== GradingResult::STATUS_PENDING_REVIEW) {
            throw InvalidEvaluationStateException::notPendingReview(
                $command->evaluationId,
                (string) $eval->evaluation_status,
            );
        }

        // ── Guard: score must be within the item's allowed range ─────────────
        $maxScore = (float) $eval->max_score_possible;

        if ($command->scoreAwarded < 0.0 || $command->scoreAwarded > $maxScore) {
            throw InvalidScoreRangeException::outOfRange($command->scoreAwarded, $maxScore);
        }

        // ── Atomic update: record the evaluator's score ───────────────────────
        $updated = DB::transaction(function () use ($command, $eval): AnswerEvaluation {
            return $this->evaluations->update($eval, [
                'evaluation_type' => GradingResult::EVAL_TYPE_MANUAL,
                'evaluation_status' => GradingResult::STATUS_SCORED,
                'score_awarded' => $command->scoreAwarded,
                'evaluator_user_id' => $command->evaluatorUserId,
                'rubric_id' => $command->rubricId,
                'rubric_criteria_json' => $command->rubricCriteriaJson,
                'evaluator_comments' => $command->evaluatorComments,
                'requires_secondary_review' => $command->requiresSecondaryReview,
                'evaluated_at' => now(),
            ]);
        });

        // ── Re-finalization: trigger if this was the last pending item ─────────
        //
        // The count is checked AFTER the update transaction so the freshly-scored
        // evaluation is included in the read. If multiple evaluators race to submit
        // the last item, both will call finalize() but finalize() is idempotent:
        // it upserts the grade and only fires ResultGenerated on the first true
        // finalization (guarded by the wasFinalBefore check inside finalize()).
        $sessionId = (string) $updated->session_id;
        $remaining = $this->evaluations->countPendingForSession($command->tenantId, $sessionId);

        if ($remaining === 0) {
            $this->triggerReFinalization($command->tenantId, $sessionId);
        }

        return $updated;
    }

    /**
     * @return Collection<int, AnswerEvaluation>
     */
    public function listPendingForSession(string $tenantId, string $sessionId): Collection
    {
        return $this->evaluations
            ->findBySession($tenantId, $sessionId)
            ->filter(
                fn (AnswerEvaluation $e): bool => $e->evaluation_status === GradingResult::STATUS_PENDING_REVIEW
            )
            ->values();
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Rebuild a synthetic ExamSessionCompleted event from the stored session row
     * and hand it to AssessmentFinalizationService::finalize() so the grade
     * calculation picks up the newly-submitted manual scores.
     *
     * This avoids adding a separate "re-finalize by session ID" method to the
     * finalization contract — the same finalize() path handles both the original
     * completion and any subsequent manual score submissions.
     */
    private function triggerReFinalization(string $tenantId, string $sessionId): void
    {
        $session = $this->sessions->findById($tenantId, $sessionId);

        if ($session === null) {
            // The session may have been purged (edge case). Log and skip rather
            // than failing the score submission that already succeeded.
            return;
        }

        $endedAt = $session->session_ended_at !== null
            ? $this->toDateTimeImmutable($session->session_ended_at)
            : new DateTimeImmutable();

        $syntheticEvent = new ExamSessionCompleted(
            sessionId: (string) $session->session_id,
            tenantId: (string) $session->tenant_id,
            candidateId: (string) $session->candidate_user_id,
            examId: (string) $session->exam_id,
            finalState: (string) $session->session_state,
            completionMethod: (string) ($session->completion_method ?? 'completed'),
            endedAt: $endedAt,
            totalQuestionsResponded: (int) $session->total_questions_responded,
            totalQuestionsFlagged: (int) $session->total_questions_flagged,
            versionLockAfter: (int) $session->version_lock,
        );

        $this->finalization->finalize($syntheticEvent);
    }

    private function toDateTimeImmutable(mixed $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        return new DateTimeImmutable((string) $value);
    }
}
