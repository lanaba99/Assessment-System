<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\QuestionTypes;

use App\Domains\QuestionBank\Enums\QuestionType;
use App\Domains\QuestionBank\Exceptions\InvalidQuestionContentException;

class QuestionTypeStrategyResolver
{
    /**
     * @var array<string, QuestionTypeStrategy>
     */
    private array $strategies = [];

    /**
     * @param  iterable<QuestionTypeStrategy>  $strategies
     */
    public function __construct(iterable $strategies)
    {
        foreach ($strategies as $strategy) {
            $this->strategies[$strategy->type()->value] = $strategy;
        }
    }

    public function for(QuestionType $type): QuestionTypeStrategy
    {
        return $this->strategies[$type->value]
            ?? throw new InvalidQuestionContentException("No strategy registered for question type: {$type->value}.");
    }
}
