<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\QuestionBank\Models\Question;
use App\Domains\QuestionBank\Models\QuestionOption;
use App\Domains\QuestionBank\Models\QuestionPsychometrics;
use App\Domains\QuestionBank\Models\QuestionVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Question
 */
class QuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Question $question */
        $question = $this->resource;
        $version = $question->currentVersion;
        $psychometrics = $version?->psychometrics;

        return [
            'id' => (string) $question->question_id,
            'tenant_id' => (string) $question->tenant_id,
            'category_id' => (string) $question->category_id,
            'title' => (string) $question->question_title,
            'type' => (string) $question->question_type,
            'bloom_level' => (int) $question->cognitive_level,
            'difficulty_level' => (int) $question->difficulty_level,
            'usage_count' => (int) $question->total_usage_count,
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
                    'is_correct' => (bool) $option->is_correct,
                ])
                ->all(),
            'psychometrics' => $psychometrics instanceof QuestionPsychometrics
                ? [
                    'p_value' => $psychometrics->difficulty_index,
                    'discrimination_index' => $psychometrics->discrimination_index,
                ]
                : null,
            'created_at' => $question->created_at?->toIso8601String(),
            'updated_at' => $question->updated_at?->toIso8601String(),
        ];
    }
}
