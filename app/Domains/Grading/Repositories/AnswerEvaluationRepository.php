<?php

declare(strict_types=1);

namespace App\Domains\Grading\Repositories;

use App\Domains\Grading\DTOs\GradingResult;
use App\Domains\Grading\Models\AnswerEvaluation;
use Illuminate\Support\Collection;

class AnswerEvaluationRepository
{
    public function __construct(
        private readonly AnswerEvaluation $model,
    ) {
    }

    public function findForSessionAndQuestion(string $sessionId, string $questionId): ?AnswerEvaluation
    {
        return $this->model
            ->newQuery()
            ->where('session_id', $sessionId)
            ->where('question_id', $questionId)
            ->first();
    }

    public function findBySession(string $sessionId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('session_id', $sessionId)
            ->get();
    }

    /**
     * Cross-domain read consumed by QuestionBank's PsychometricAnalysisService.
     * Filters by the `question_version_id` denormalized into evaluation_metadata.
     */
    public function findByQuestionVersionId(string $questionVersionId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('evaluation_metadata->question_version_id', $questionVersionId)
            ->get();
    }

    /**
     * Aggregate session-total scores in SQL for the given session ids.
     *
     * @param  array<int, string>  $sessionIds
     * @return array<string, float>  keyed by session_id
     */
    public function getSessionTotalScores(array $sessionIds): array
    {
        if ($sessionIds === []) {
            return [];
        }

        $rows = $this->model
            ->newQuery()
            ->select('session_id')
            ->selectRaw('SUM(score_awarded) AS total_score')
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

        $existing = $this->findForSessionAndQuestion($result->sessionId, $result->questionId);

        if ($existing !== null) {
            $existing->forceFill($attributes)->save();

            return $existing;
        }

        return $this->model->newQuery()->create($attributes);
    }
}
