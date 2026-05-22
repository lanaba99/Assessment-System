<?php

declare(strict_types=1);

namespace App\Domains\Rules\Contracts;

use App\Domains\Rules\DTOs\EligibilityContext;
use App\Domains\Rules\Models\EligibilityChain;

interface ConditionEvaluator
{
    public function supports(string $conditionType): bool;

    /**
     * @return array{passed: bool, reason: ?string}
     */
    public function evaluate(EligibilityChain $step, EligibilityContext $context): array;
}
