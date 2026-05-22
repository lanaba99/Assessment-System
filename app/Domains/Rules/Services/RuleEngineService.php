<?php

declare(strict_types=1);

namespace App\Domains\Rules\Services;

use App\Domains\Rules\Contracts\ConditionEvaluator;
use App\Domains\Rules\DTOs\EligibilityContext;
use App\Domains\Rules\Models\EligibilityChain;

/**
 * Per-condition evaluation engine. Delegates to the registered ConditionEvaluator
 * whose supports() returns true for the step's condition_type.
 */
class RuleEngineService
{
    /**
     * @param  iterable<ConditionEvaluator>  $evaluators
     */
    public function __construct(
        private readonly iterable $evaluators,
    ) {
    }

    /**
     * @return array{passed: bool, reason: ?string, was_overridden: bool}
     */
    public function evaluateStep(EligibilityChain $step, EligibilityContext $context): array
    {
        if ($this->isOverridden($step)) {
            return [
                'passed' => true,
                'reason' => "Step {$step->chain_step_number} satisfied via administrative override.",
                'was_overridden' => true,
            ];
        }

        $evaluator = $this->resolveEvaluator((string) $step->condition_type);

        if ($evaluator === null) {
            return [
                'passed' => false,
                'reason' => "No evaluator registered for condition type '{$step->condition_type}'.",
                'was_overridden' => false,
            ];
        }

        $outcome = $evaluator->evaluate($step, $context);

        return [
            'passed' => (bool) $outcome['passed'],
            'reason' => $outcome['reason'] ?? null,
            'was_overridden' => false,
        ];
    }

    private function resolveEvaluator(string $conditionType): ?ConditionEvaluator
    {
        foreach ($this->evaluators as $evaluator) {
            if ($evaluator->supports($conditionType)) {
                return $evaluator;
            }
        }

        return null;
    }

    private function isOverridden(EligibilityChain $step): bool
    {
        return (bool) $step->is_satisfied_override_available
            && $step->override_authorized_by_user_id !== null;
    }
}
