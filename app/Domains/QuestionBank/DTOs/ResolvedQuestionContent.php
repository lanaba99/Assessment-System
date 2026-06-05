<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\DTOs;

/**
 * The persistable shape a strategy produces from a QuestionContentDraft: the
 * option rows to create, the answer key to store on the version, and any
 * evaluator instructions. Everything here maps directly onto question_versions
 * (+ question_options).
 */
final readonly class ResolvedQuestionContent
{
    /**
     * @param  array<int, array{option_text: string, is_correct: bool, option_sequence: int, option_metadata?: array<string, mixed>|null}>  $options
     * @param  array<string, mixed>|null  $correctAnswer          Stored as correct_answer_json.
     * @param  array<string, mixed>|null  $evaluatorInstructions  Stored as evaluator_instructions.
     */
    public function __construct(
        public array $options = [],
        public ?array $correctAnswer = null,
        public ?array $evaluatorInstructions = null,
    ) {
    }
}
