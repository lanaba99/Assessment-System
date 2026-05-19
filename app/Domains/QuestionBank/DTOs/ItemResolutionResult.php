<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\DTOs;

use Illuminate\Support\Collection;

final readonly class ItemResolutionResult
{
    public function __construct(
        public Collection $items,
        public array $achievedCompetencyMix,
        public array $achievedBloomMix,
        public float $achievedAverageDifficulty,
        public float $achievedAverageDiscrimination,
        public array $coverageGaps,
        public string $strategyUsed,
    ) {
    }

    public function isFullyResolved(int $requested): bool
    {
        return $this->items->count() === $requested && $this->coverageGaps === [];
    }
}
