<?php

declare(strict_types=1);

namespace App\Domains\Grading\DTOs;

/**
 * Immutable output produced by WeightedScoringService::compute().
 *
 * Carries both the flat raw totals and the blueprint-weighted result so
 * callers can decide which to surface (flat for backward compat, weighted for
 * Grade persistence).
 *
 * sectionBreakdown shape (keyed by section_id):
 *   [
 *     'section_id'            => string,
 *     'evaluated_count'       => int,     questions answered in this section
 *     'awarded'               => float,   sum of score_awarded
 *     'max'                   => float,   sum of max_score_possible
 *     'section_percentage'    => float,   awarded/max × 100 (0-100)
 *     'weight'                => float,   total min_weight_percentage for this section
 *     'weighted_contribution' => float,   section_percentage/100 × weight
 *   ]
 *
 * competencyBreakdown shape (keyed by competency_id):
 *   [
 *     'competency_id'               => string,
 *     'total_weighted_contribution' => float,  sum of (section_% / 100 × blueprint_weight)
 *     'total_weight'                => float,  sum of blueprint min_weight_percentage
 *     'section_count'               => int,
 *     'section_ids'                 => string[],
 *   ]
 */
final readonly class WeightedScoreResult
{
    /**
     * @param  array<string, array<string, mixed>>  $sectionBreakdown
     * @param  array<string, array<string, mixed>>  $competencyBreakdown
     */
    public function __construct(
        // Flat (unweighted) totals — preserved for reference and raw_score persistence.
        public float $rawScore,
        public float $rawMaxScore,

        // Blueprint-weighted result — primary score when blueprints are present.
        public float $weightedPercentage,
        public float $totalWeight,

        // Diagnostics
        public int $unmappedEvaluationCount,
        public array $sectionBreakdown,
        public array $competencyBreakdown,
    ) {
    }

    /**
     * True when at least one blueprint matched session items.
     * When false, the caller should fall back to the flat raw percentage.
     */
    public function hasBlueprints(): bool
    {
        return $this->totalWeight > 0.0;
    }
}
