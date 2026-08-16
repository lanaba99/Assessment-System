<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Services;

/**
 * Simplified 1PL (Rasch) ability updater for live CAT.
 *
 * Uses a Robbins-Monro stochastic-approximation step rather than full
 * Newton-Raphson MLE. This is a deliberate simplification appropriate for
 * a graduation-project-scale item pool (MLE is unstable / can diverge with
 * only a handful of administered items, especially on an all-correct or
 * all-incorrect streak). Theta moves toward "more able" on a correct
 * answer and "less able" on an incorrect one; the step size shrinks as
 * more items are administered (1/sqrt(n) cooling), which is the standard
 * way to guarantee convergence for this class of estimator.
 *
 * The probability model (logistic on the difficulty-vs-theta logit) is the
 * same 1PL model AdaptiveCATStrategy already uses for item information, so
 * item selection and ability updates stay consistent with each other.
 */
final class AbilityEstimationService
{
    private const INITIAL_STEP = 1.0;

    /**
     * theta_{n+1} = theta_n + step_n * (observed - P(theta_n))
     */
    public function update(float $theta, float $itemDifficultyLogit, bool $isCorrect, int $administeredCountBefore): float
    {
        $p = $this->probabilityCorrect($itemDifficultyLogit, $theta);
        $step = self::INITIAL_STEP / sqrt($administeredCountBefore + 1);
        $observed = $isCorrect ? 1.0 : 0.0;

        return $theta + $step * ($observed - $p);
    }

    /**
     * Standard CAT formula: SE(theta) = 1 / sqrt(sum of item informations
     * for every item administered so far).
     *
     * @param  array<int, float>  $administeredItemInformations
     */
    public function standardError(array $administeredItemInformations): float
    {
        $totalInformation = array_sum($administeredItemInformations);

        // Before any item has been administered, uncertainty is unbounded.
        // Returning a large-but-finite number keeps the stopping-rule
        // comparison (standardError <= stoppingStandardError) well-defined.
        return $totalInformation <= 0.0 ? 999.0 : 1.0 / sqrt($totalInformation);
    }

    /**
     * Same formula as standardError(), but takes an already-accumulated
     * total (callers that update the running total incrementally, instead
     * of keeping every item's information in an array, use this).
     */
    public function standardErrorFromTotal(float $totalInformation): float
    {
        return $totalInformation <= 0.0 ? 999.0 : 1.0 / sqrt($totalInformation);
    }

    public function itemInformation(float $itemDifficultyLogit, float $theta): float
    {
        $p = $this->probabilityCorrect($itemDifficultyLogit, $theta);

        return $p * (1.0 - $p);
    }

    public function logitDifficulty(float $difficultyIndex): float
    {
        $p = max(0.01, min(0.99, $difficultyIndex));

        return log((1.0 - $p) / $p);
    }

    private function probabilityCorrect(float $itemDifficultyLogit, float $theta): float
    {
        return 1.0 / (1.0 + exp($itemDifficultyLogit - $theta));
    }
}