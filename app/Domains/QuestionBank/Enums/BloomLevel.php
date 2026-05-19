<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Enums;

enum BloomLevel: int
{
    case Remember = 1;
    case Understand = 2;
    case Apply = 3;
    case Analyze = 4;
    case Evaluate = 5;
    case Create = 6;

    public function label(): string
    {
        return match ($this) {
            self::Remember => 'Remember',
            self::Understand => 'Understand',
            self::Apply => 'Apply',
            self::Analyze => 'Analyze',
            self::Evaluate => 'Evaluate',
            self::Create => 'Create',
        };
    }

    public function isHigherOrder(): bool
    {
        return $this->value >= self::Analyze->value;
    }

    public static function values(): array
    {
        return array_map(static fn (self $case): int => $case->value, self::cases());
    }
}
