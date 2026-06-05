<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\QuestionTypes;

use App\Domains\QuestionBank\DTOs\QuestionContentDraft;
use App\Domains\QuestionBank\DTOs\ResolvedQuestionContent;
use App\Domains\QuestionBank\Enums\QuestionType;
use App\Domains\QuestionBank\Exceptions\InvalidQuestionContentException;

class TrueFalseStrategy implements QuestionTypeStrategy
{
    public function type(): QuestionType
    {
        return QuestionType::TrueFalse;
    }

    public function validate(QuestionContentDraft $draft): void
    {
        if (! array_key_exists('correct_answer', $draft->answer)) {
            throw new InvalidQuestionContentException('True/False questions require a correct_answer.');
        }

        if (! is_bool($draft->answer['correct_answer'])) {
            throw new InvalidQuestionContentException('The true/false correct_answer must be a boolean.');
        }
    }

    public function resolve(QuestionContentDraft $draft): ResolvedQuestionContent
    {
        $value = (bool) $draft->answer['correct_answer'];

        // Two canonical, gradable options so true/false flows through the same
        // option-based grading path as MCQ.
        $options = [
            [
                'option_text' => 'True',
                'is_correct' => $value === true,
                'option_sequence' => 1,
                'option_metadata' => ['value' => true],
            ],
            [
                'option_text' => 'False',
                'is_correct' => $value === false,
                'option_sequence' => 2,
                'option_metadata' => ['value' => false],
            ],
        ];

        return new ResolvedQuestionContent(
            options: $options,
            correctAnswer: ['value' => $value],
        );
    }
}
