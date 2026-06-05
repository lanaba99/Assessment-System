<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\QuestionTypes;

use App\Domains\QuestionBank\DTOs\QuestionContentDraft;
use App\Domains\QuestionBank\DTOs\ResolvedQuestionContent;
use App\Domains\QuestionBank\Enums\QuestionType;
use App\Domains\QuestionBank\Exceptions\InvalidQuestionContentException;

class MultipleChoiceStrategy implements QuestionTypeStrategy
{
    public function type(): QuestionType
    {
        return QuestionType::MultipleChoice;
    }

    public function validate(QuestionContentDraft $draft): void
    {
        if (count($draft->choices) < 2) {
            throw new InvalidQuestionContentException('Multiple-choice questions require at least two options.');
        }

        $hasCorrect = false;

        foreach ($draft->choices as $choice) {
            if (trim((string) ($choice['option_text'] ?? '')) === '') {
                throw new InvalidQuestionContentException('Every multiple-choice option must have text.');
            }

            if (! empty($choice['is_correct'])) {
                $hasCorrect = true;
            }
        }

        if (! $hasCorrect) {
            throw new InvalidQuestionContentException('At least one option must be marked correct.');
        }
    }

    public function resolve(QuestionContentDraft $draft): ResolvedQuestionContent
    {
        $options = [];

        foreach (array_values($draft->choices) as $index => $choice) {
            $options[] = [
                'option_text' => (string) $choice['option_text'],
                'is_correct' => (bool) ($choice['is_correct'] ?? false),
                'option_sequence' => (int) ($choice['option_sequence'] ?? ($index + 1)),
                'option_metadata' => $choice['option_metadata'] ?? null,
            ];
        }

        // Truth lives in question_options.is_correct; no separate answer key.
        return new ResolvedQuestionContent(options: $options);
    }
}
