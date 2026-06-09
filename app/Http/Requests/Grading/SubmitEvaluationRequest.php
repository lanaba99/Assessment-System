<?php

declare(strict_types=1);

namespace App\Http\Requests\Grading;

use App\Domains\Grading\DTOs\SubmitEvaluationCommand;
use App\Domains\Grading\Models\AnswerEvaluation;
use App\Domains\Grading\Repositories\AnswerEvaluationRepository;
use Illuminate\Foundation\Http\FormRequest;

class SubmitEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $tenantId = (string) tenant()->getKey();
        $eval = app(AnswerEvaluationRepository::class)
            ->findById($tenantId, (string) $this->route('evaluationId'));

        if ($eval === null) {
            abort(404, 'Evaluation not found.');
        }

        return $user->can('submitEvaluation', $eval);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'score_awarded' => ['required', 'numeric', 'min:0'],
            'rubric_id' => ['nullable', 'uuid'],
            'rubric_criteria_json' => ['nullable', 'array'],
            'evaluator_comments' => ['nullable', 'array'],
            'requires_secondary_review' => ['nullable', 'boolean'],
        ];
    }

    public function toCommand(string $tenantId, string $evaluationId): SubmitEvaluationCommand
    {
        $validated = $this->validated();

        return new SubmitEvaluationCommand(
            tenantId: $tenantId,
            evaluationId: $evaluationId,
            evaluatorUserId: (string) $this->user()->id,
            scoreAwarded: (float) $validated['score_awarded'],
            rubricId: isset($validated['rubric_id']) ? (string) $validated['rubric_id'] : null,
            rubricCriteriaJson: $validated['rubric_criteria_json'] ?? null,
            evaluatorComments: $validated['evaluator_comments'] ?? null,
            requiresSecondaryReview: (bool) ($validated['requires_secondary_review'] ?? false),
        );
    }
}
