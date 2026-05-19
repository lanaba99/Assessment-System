<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Strategies;

use InvalidArgumentException;

class ItemResolutionStrategyResolver
{
    public function __construct(
        private readonly RandomWithinBucketsStrategy $random,
        private readonly StratifiedBloomStrategy $stratified,
        private readonly GreedyDiscriminationStrategy $greedy,
        private readonly AdaptiveCATStrategy $adaptive,
    ) {
    }

    public function resolve(string $name): ItemResolutionStrategy
    {
        return match ($name) {
            'random' => $this->random,
            'stratified' => $this->stratified,
            'greedy' => $this->greedy,
            'adaptive' => $this->adaptive,
            default => throw new InvalidArgumentException("Unknown item-resolution strategy: {$name}"),
        };
    }

    public function adaptive(): AdaptiveCATStrategy
    {
        return $this->adaptive;
    }
}
