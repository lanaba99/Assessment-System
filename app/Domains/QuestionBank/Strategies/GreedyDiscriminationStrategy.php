<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Strategies;

use App\Domains\QuestionBank\DTOs\ItemResolutionRequest;
use Illuminate\Support\Collection;

class GreedyDiscriminationStrategy implements ItemResolutionStrategy
{
    public function name(): string
    {
        return 'greedy';
    }

    public function resolve(ItemResolutionRequest $request, Collection $eligiblePool): Collection
    {
        $scored = $eligiblePool->map(function ($version) use ($request): array {
            return [
                'version' => $version,
                'score' => $this->fitnessScore($version, $request->targetDifficulty),
            ];
        });

        return $scored
            ->sortByDesc('score')
            ->take($request->itemCount)
            ->pluck('version')
            ->values();
    }

    private function fitnessScore(object $version, float $targetDifficulty): float
    {
        $d = (float) ($version->psychometrics?->discrimination_index ?? 0.0);
        $p = (float) ($version->psychometrics?->difficulty_index ?? $targetDifficulty);

        $difficultyAlignment = 1.0 - min(1.0, abs($p - $targetDifficulty) * 2.0);

        return ($d * 0.7) + ($difficultyAlignment * 0.3);
    }
}
