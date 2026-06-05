<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\QuestionTypes;

use App\Domains\QuestionBank\DTOs\QuestionContentDraft;
use App\Domains\QuestionBank\DTOs\ResolvedQuestionContent;
use App\Domains\QuestionBank\Enums\QuestionType;

class EssayStrategy implements QuestionTypeStrategy
{
    public function type(): QuestionType
    {
        return QuestionType::Essay;
    }

    public function validate(QuestionContentDraft $draft): void
    {
        // Essays are human-graded: no answer key to validate. Evaluator
        // instructions are optional guidance, not a correctness requirement.
    }

    public function resolve(QuestionContentDraft $draft): ResolvedQuestionContent
    {
        return new ResolvedQuestionContent(
            evaluatorInstructions: $draft->evaluatorInstructions,
        );
    }
}
