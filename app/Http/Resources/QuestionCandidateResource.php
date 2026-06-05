<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\QuestionBank\Models\Question;
use App\Domains\QuestionBank\Models\QuestionOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Candidate-facing view of a question, for exam delivery.
 *
 * SECURITY: deliberately omits every field that would reveal the answer key or
 * item analytics — `is_correct`, `correct_answer_json`, `psychometrics`,
 * `evaluator_instructions`, and option metadata. Use THIS resource (never
 * QuestionResource, which is admin-only) on any route a test-taker can reach.
 *
 * @mixin Question
 */
class QuestionCandidateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Question $question */
        $question = $this->resource;
        $version = $question->currentVersion;

        return [
            'id' => (string) $question->question_id,
            'category_id' => (string) $question->category_id,
            'type' => (string) $question->question_type,
            'question_text' => $version?->question_text,
            'stem' => $version?->question_stem,
            'version_id' => $version?->version_id,
            'choices' => $version?->options
                ?->sortBy('option_sequence')
                ->values()
                ->map(static fn (QuestionOption $option): array => [
                    'id' => (string) $option->option_id,
                    'option_sequence' => (int) $option->option_sequence,
                    'option_text' => (string) $option->option_text,
                ])
                ->all(),
        ];
    }
}
