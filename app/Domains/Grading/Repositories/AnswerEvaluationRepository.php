<?php

declare(strict_types=1);

namespace App\Domains\Grading\Repositories;

use App\Domains\Grading\DTOs\GradingResult;
use App\Domains\Grading\Models\AnswerEvaluation;
use Illuminate\Support\Collection;

/**
 * Tenant isolation is enforced by explicit where('tenant_id') on every query.
 * AnswerEvaluation uses AutoFillsTenantId (no global scope), so this repository
 * is the primary isolation layer for grading data.
 *
 * All read methods require $tenantId as their first parameter — this prevents
 * cross-tenant data access if a session_id UUID were ever to collide, and makes
 * tenant ownership explicit at every call site.
 */
class AnswerEvaluationRepository
{
    public function __construct(
        private readonly AnswerEvaluation $model,
    ) {
    }

    public function findById(string $tenantId, string $evaluationId): ?AnswerEvaluation
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->whereKey($evaluationId)
            ->first();
    }

    /**
     * Persist a set of attribute changes on an existing evaluation row.
     * Uses forceFill so evaluator_user_id and other server-controlled columns
     * are written regardless of the model's $fillable list.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(AnswerEvaluation $eval, array $attributes): AnswerEvaluation
    {
        $eval->forceFill($attributes)->save();

        return $eval;
    }

    /**
     * Count evaluations still awaiting human review for the given session.
     * Used by ManualEvaluationServiceImpl to decide whether to trigger
     * grade re-finalization after a score is submitted.
     */
    public function countPendingForSession(string $tenantId, string $sessionId): int
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('session_id', $sessionId)
            ->where('evaluation_status', 'pending_review')
            ->count();
    }

    public function findForSessionAndQuestion(
        string $tenantId,
        string $sessionId,
        string $questionId,
    ): ?AnswerEvaluation {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('session_id', $sessionId)
            ->where('question_id', $questionId)
            ->first();
    }

    /**
     * @return Collection<int, AnswerEvaluation>
     */
    public function findBySession(string $tenantId, string $sessionId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('session_id', $sessionId)
            ->get();
    }

    /**
     * Cross-domain read consumed by QuestionBank's PsychometricAnalysisService.
     * Scoped to a single tenant so psychometric stats remain per-tenant.
     *
     * @return Collection<int, AnswerEvaluation>
     */
    public function findByQuestionVersionId(string $tenantId, string $questionVersionId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('evaluation_metadata->question_version_id', $questionVersionId)
            ->get();
    }

    /**
     * Aggregate session-total scores in SQL for the given session ids.
     * The tenant filter ensures scores from other tenants never pollute the
     * psychometric calculations even if session UUIDs were to collide.
     *
     * @param  array<int, string>  $sessionIds
     * @return array<string, float>  keyed by session_id
     */
    public function getSessionTotalScores(string $tenantId, array $sessionIds): array
    {
        if ($sessionIds === []) {
            return [];
        }

        $rows = $this->model
            ->newQuery()
            ->select('session_id')
            ->selectRaw('SUM(score_awarded) AS total_score')
            ->where('tenant_id', $tenantId)
            ->whereIn('session_id', $sessionIds)
            ->groupBy('session_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->session_id] = (float) $row->total_score;
        }

        return $map;
    }

    public function record(GradingResult $result, string $tenantId): AnswerEvaluation
    {
        $now = now();

        $attributes = [
            'session_id' => $result->sessionId,
            'question_id' => $result->questionId,
            'evaluator_user_id' => null,
            'tenant_id' => $tenantId,
            'rubric_id' => null,
            'evaluation_type' => $result->evaluationType,
            'rubric_criteria_json' => null,
            'score_awarded' => $result->rawScore,
            'max_score_possible' => $result->maxScore,
            'evaluation_status' => $result->evaluationStatus,
            'evaluator_comments' => null,
            'evaluation_metadata' => array_merge($result->evaluationMetadata, [
                'session_item_id' => $result->sessionItemId,
                'question_version_id' => $result->questionVersionId,
                'normalized_score' => $result->normalizedScore,
                'is_correct' => $result->isCorrect,
            ]),
            'requires_secondary_review' => $result->requiresSecondaryReview,
            'secondary_reviewer_id' => null,
            'evaluated_at' => $result->isAutoScored() ? $now : null,
            'secondary_reviewed_at' => null,
            'created_at' => $now,
        ];

        // Pass $tenantId through to the scoped idempotency check so that an
        // unlikely UUID collision with another tenant's session cannot cause
        // the wrong row to be overwritten.
        $existing = $this->findForSessionAndQuestion($tenantId, $result->sessionId, $result->questionId);

        if ($existing !== null) {
            $existing->forceFill($attributes)->save();

            return $existing;
        }

        return $this->model->newQuery()->create($attributes);
    }
}
