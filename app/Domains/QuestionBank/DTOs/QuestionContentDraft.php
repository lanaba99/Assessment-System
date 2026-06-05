<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\DTOs;

use App\Domains\QuestionBank\Enums\QuestionType;

/**
 * The raw, type-agnostic content an author submitted for one version of a
 * question. A QuestionTypeStrategy interprets the fields relevant to its type
 * and ignores the rest.
 */
final readonly class QuestionContentDraft
{
    /**
     * @param  array<int, array{option_text: string, is_correct?: bool, option_sequence?: int, option_metadata?: array<string, mixed>|null}>  $choices
     * @param  array<string, mixed>  $answer            Type-specific answer payload (e.g. correct_answer, accepted_answers, match_mode).
     * @param  array<string, mixed>|null  $evaluatorInstructions  Free-form guidance for human grading (essay / hybrid).
     */
    public function __construct(
        public QuestionType $type,
        public string $questionText,
        public ?string $stem = null,
        public array $choices = [],
        public array $answer = [],
        public ?array $evaluatorInstructions = null,
    ) {
    }
}
