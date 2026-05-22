<?php

declare(strict_types=1);

namespace App\Domains\Grading\Strategies;

use App\Domains\Grading\Contracts\GradingStrategy;

class GradingStrategyResolver
{
    /**
     * @param  iterable<GradingStrategy>  $strategies
     */
    public function __construct(
        private readonly iterable $strategies,
        private readonly GradingStrategy $fallback,
    ) {
    }

    public function resolve(string $questionType): GradingStrategy
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($questionType)) {
                return $strategy;
            }
        }

        return $this->fallback;
    }
}
