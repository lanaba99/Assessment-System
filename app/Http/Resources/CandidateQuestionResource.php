<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\QuestionBank\Models\QuestionVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Safe, candidate-facing view of a question version.
 * Deliberately excludes correct_answer_json, is_correct, explanation_text,
 * and evaluator_instructions — this resource is served to the exam taker
 * while the exam is in progress, so the answer must never leak here.
 *
 * @property-read QuestionVersion $resource
 */
class CandidateQuestionResource extends JsonResource
{
    public static $wrap = 'data';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $version = $this->resource;

        return [
            'question_version_id' => $version->version_id,
            'question_type' => $version->question_type,
            'question_text' => $version->question_text,
            'question_stem' => $version->question_stem,
            'choices' => $version->options
                ->sortBy('option_sequence')
                ->values()
                ->map(fn ($option) => [
                    'option_id' => $option->option_id,
                    'option_text' => $option->option_text,
                    'option_sequence' => $option->option_sequence,
                    // is_correct is intentionally omitted.
                ]),
        ];
    }
}