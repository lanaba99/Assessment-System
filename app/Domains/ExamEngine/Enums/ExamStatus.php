<?php

declare(strict_types=1);

namespace App\Domains\ExamEngine\Enums;

enum ExamStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => $target === self::Published || $target === self::Archived,
            self::Published => $target === self::Archived,
            self::Archived => false,
        };
    }

    public function isPublished(): bool
    {
        return $this === self::Published;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
