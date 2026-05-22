<?php

declare(strict_types=1);

namespace App\Domains\Rules\Services;

use App\Domains\Rules\DTOs\EligibilityContext;
use App\Domains\Rules\DTOs\EligibilityResult;
use App\Domains\Rules\Models\EligibilityChain;
use App\Domains\Rules\Repositories\EligibilityChainRepository;

class EligibilityEvaluatorService
{
    private const OP_AND = 'AND';

    private const OP_OR = 'OR';

    public function __construct(
        private readonly EligibilityChainRepository $chainRepository,
        private readonly RuleEngineService $ruleEngine,
    ) {
    }

    public function evaluate(EligibilityContext $context): EligibilityResult
    {
        $chain = $this->chainRepository->findForExam($context->tenantId, $context->examId);

        // No chain configured for this exam = unconditionally eligible.
        if ($chain->isEmpty()) {
            return new EligibilityResult(
                candidateId: $context->candidateId,
                examId: $context->examId,
                isEligible: true,
                stepOutcomes: [],
            );
        }

        $stepOutcomes = [];
        $aggregate = null;
        $previousOperator = self::OP_AND;

        foreach ($chain as $step) {
            $outcome = $this->ruleEngine->evaluateStep($step, $context);

            $stepOutcomes[] = [
                'step' => (int) $step->chain_step_number,
                'condition_type' => (string) $step->condition_type,
                'passed' => $outcome['passed'],
                'reason' => $outcome['reason'],
                'was_overridden' => $outcome['was_overridden'],
            ];

            $aggregate = $aggregate === null
                ? $outcome['passed']
                : $this->combine($aggregate, $outcome['passed'], $previousOperator);

            $previousOperator = $this->normalizeOperator($step->logical_operator);
        }

        $isEligible = (bool) $aggregate;

        return new EligibilityResult(
            candidateId: $context->candidateId,
            examId: $context->examId,
            isEligible: $isEligible,
            stepOutcomes: $stepOutcomes,
            rejectionReason: $isEligible ? null : $this->firstFailureReason($stepOutcomes),
        );
    }

    private function combine(bool $left, bool $right, string $operator): bool
    {
        return match ($operator) {
            self::OP_OR => $left || $right,
            default => $left && $right,
        };
    }

    private function normalizeOperator(?string $operator): string
    {
        $upper = strtoupper((string) $operator);

        return $upper === self::OP_OR ? self::OP_OR : self::OP_AND;
    }

    /**
     * @param  array<int, array<string, mixed>>  $stepOutcomes
     */
    private function firstFailureReason(array $stepOutcomes): ?string
    {
        foreach ($stepOutcomes as $outcome) {
            if ($outcome['passed'] === false) {
                return (string) ($outcome['reason'] ?? "Step {$outcome['step']} failed.");
            }
        }

        return null;
    }
}
