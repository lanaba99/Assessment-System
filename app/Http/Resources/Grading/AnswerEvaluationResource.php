<?php

declare(strict_types=1);

namespace App\Http\Resources\Grading;

use App\Domains\Grading\Models\AnswerEvaluation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AnswerEvaluation
 */
class AnswerEvaluationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->evaluation_id,
            'session_id' => (string) $this->session_id,
            'question_id' => (string) $this->question_id,
            'tenant_id' => (string) $this->tenant_id,
            'evaluator_user_id' => $this->evaluator_user_id !== null
                ? (string) $this->evaluator_user_id
                : null,
            'rubric_id' => $this->rubric_id !== null ? (string) $this->rubric_id : null,
            'evaluation_type' => (string) $this->evaluation_type,
            'evaluation_status' => (string) $this->evaluation_status,
            'score_awarded' => $this->score_awarded !== null ? (float) $this->score_awarded : null,
            'max_score_possible' => $this->max_score_possible !== null
                ? (float) $this->max_score_possible
                : null,
            'rubric_criteria_json' => $this->rubric_criteria_json,
            'evaluator_comments' => $this->evaluator_comments,
            // evaluation_metadata carries session_item_id and question_version_id —
            // useful for the evaluator UI to deep-link to the original question.
            'evaluation_metadata' => $this->evaluation_metadata,
            'requires_secondary_review' => (bool) $this->requires_secondary_review,
            'secondary_reviewer_id' => $this->secondary_reviewer_id !== null
                ? (string) $this->secondary_reviewer_id
                : null,
            'evaluated_at' => $this->evaluated_at?->toIso8601String(),
            'secondary_reviewed_at' => $this->secondary_reviewed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
