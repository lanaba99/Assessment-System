<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Enums;

enum QuestionType: string
{
    case MultipleChoice = 'mcq';
    case TrueFalse = 'true_false';
    case ShortAnswer = 'short_answer';
    case Essay = 'essay';

    /**
     * Whether this type persists discrete option rows (and therefore renders
     * a choice list to the candidate).
     */
    public function hasOptions(): bool
    {
        return $this === self::MultipleChoice || $this === self::TrueFalse;
    }

    /**
     * Whether the correct answer can be determined by the engine without a
     * human evaluator.
     */
    public function isAutoGradable(): bool
    {
        return $this !== self::Essay;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
