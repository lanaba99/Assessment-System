<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Strategies;

use App\Domains\QuestionBank\DTOs\AdaptiveContext;
use App\Domains\QuestionBank\DTOs\ItemResolutionRequest;
use Illuminate\Support\Collection;
use LogicException;

class AdaptiveCATStrategy implements ItemResolutionStrategy
{
    public function name(): string
    {
        return 'adaptive';
    }

    public function resolve(ItemResolutionRequest $request, Collection $eligiblePool): Collection
    {
        throw new LogicException(
            'AdaptiveCATStrategy is item-by-item; use selectNextItem(AdaptiveContext) instead of bulk resolve().'
        );
    }

    public function selectNextItem(AdaptiveContext $context, Collection $eligiblePool): ?object
    {
        $remaining = $eligiblePool->whereNotIn('version_id', $context->administeredVersionIds);

        if ($remaining->isEmpty()) {
            return null;
        }

        return $remaining
            ->sortByDesc(fn ($version): float => $this->itemInformation($version, $context->abilityEstimate))
            ->first();
    }

    private function itemInformation(object $version, float $theta): float
    {
        $b = $this->logitDifficulty($version);
        $p = 1.0 / (1.0 + exp($b - $theta));

        return $p * (1.0 - $p);
    }

    private function logitDifficulty(object $version): float
    {
        $p = (float) ($version->psychometrics?->difficulty_index ?? 0.5);
        $p = max(0.01, min(0.99, $p));

        return log((1.0 - $p) / $p);
    }
}
