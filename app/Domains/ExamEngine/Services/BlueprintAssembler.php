<?php

declare(strict_types=1);

namespace App\Domains\ExamEngine\Services;

use App\Domains\ExamEngine\Models\ExamSection;
use App\Domains\QuestionBank\DTOs\BlueprintSpecification;
use App\Domains\QuestionBank\DTOs\ItemResolutionRequest;
use Illuminate\Support\Collection;

/**
 * Aggregates an ExamSection's blueprint rows into the flat DTOs that
 * QuestionBankService expects.
 *
 * Each ExamBlueprint row specifies one competency's contribution to a section.
 * A section may have N blueprint rows. The assembler:
 *   - normalises weight percentages to sum to 100
 *   - computes a questions-weighted average bloom distribution and difficulty
 *   - uses the strictest (maximum) minDiscrimination across blueprints
 *   - resolves the most-frequent resolution_strategy
 */
final class BlueprintAssembler
{
    /**
     * Build the coverage-analysis spec for a section at publish time.
     *
     * @param  Collection<int, \App\Domains\ExamEngine\Models\ExamBlueprint>  $blueprints
     */
    public static function toSpec(
        ExamSection $section,
        Collection $blueprints,
        string $tenantId,
        string $examId,
    ): BlueprintSpecification {
        [$competencyWeights, $bloomDistribution, $targetDifficulty, $minDiscrimination] =
            self::aggregate($section, $blueprints);

        return new BlueprintSpecification(
            tenantId: $tenantId,
            examId: $examId,
            totalQuestions: self::itemCount($section, $blueprints),
            competencyWeights: $competencyWeights,
            bloomDistribution: $bloomDistribution,
            targetDifficulty: $targetDifficulty,
            minDiscrimination: $minDiscrimination,
            requireCalibrated: true,
        );
    }

    /**
     * Build the item-resolution request for a section at session-start time.
     *
     * @param  Collection<int, \App\Domains\ExamEngine\Models\ExamBlueprint>  $blueprints
     * @param  array<int, string>  $excludedVersionIds
     */
    public static function toRequest(
        ExamSection $section,
        Collection $blueprints,
        string $tenantId,
        string $candidateId,
        array $excludedVersionIds = [],
    ): ItemResolutionRequest {
        [$competencyWeights, $bloomDistribution, $targetDifficulty, $minDiscrimination] =
            self::aggregate($section, $blueprints);

        $strategy = self::dominantStrategy($blueprints);

        return new ItemResolutionRequest(
            tenantId: $tenantId,
            candidateId: $candidateId,
            itemCount: self::itemCount($section, $blueprints),
            competencyWeights: $competencyWeights,
            bloomDistribution: $bloomDistribution,
            targetDifficulty: $targetDifficulty,
            minDiscrimination: $minDiscrimination,
            excludedQuestionVersionIds: $excludedVersionIds,
            requireCalibrated: true,
            strategy: $strategy,
        );
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Core aggregation: competency weights, bloom distribution, difficulty,
     * and min discrimination — all derived from the section's blueprint rows.
     *
     * Returns: [$competencyWeights, $bloomDistribution, $targetDifficulty, $minDiscrimination]
     *
     * @param  Collection<int, \App\Domains\ExamEngine\Models\ExamBlueprint>  $blueprints
     * @return array{array<string, float>, array<string|int, int>, float, float}
     */
    private static function aggregate(ExamSection $section, Collection $blueprints): array
    {
        $totalItems = self::itemCount($section, $blueprints);

        // ── Competency weights (normalise to sum to 100) ──────────────────────
        $rawWeights = $blueprints->mapWithKeys(
            static fn ($bp): array => [(string) $bp->competency_id => (float) $bp->min_weight_percentage],
        )->toArray();

        $weightSum = array_sum($rawWeights) ?: count($rawWeights);

        $competencyWeights = array_map(
            static fn (float $w): float => round(($w / $weightSum) * 100, 4),
            $rawWeights,
        );

        // ── Bloom distribution (questions-count weighted average) ─────────────
        $bloomAccumulator = [];

        foreach ($blueprints as $bp) {
            $dist = is_array($bp->bloom_distribution) ? $bp->bloom_distribution : [];
            $bpWeight = $totalItems > 0 ? (int) $bp->min_questions_count / $totalItems : 0.0;

            foreach ($dist as $level => $share) {
                $bloomAccumulator[$level] = ($bloomAccumulator[$level] ?? 0.0) + ((float) $share * $bpWeight);
            }
        }

        $bloomTotal = array_sum($bloomAccumulator) ?: 1;
        $bloomDistribution = array_map(
            static fn (float $v): int => (int) round($v / $bloomTotal * 100),
            $bloomAccumulator,
        );

        // ── Target difficulty (questions-count weighted average) ──────────────
        $targetDifficulty = 0.60;

        if ($totalItems > 0) {
            $weightedSum = $blueprints->sum(
                static fn ($bp): float => (float) $bp->target_difficulty * (int) $bp->min_questions_count,
            );
            $targetDifficulty = $weightedSum / $totalItems;
        }

        // ── Min discrimination (strictest across blueprints) ──────────────────
        $minDiscrimination = (float) ($blueprints->max('min_discrimination') ?? 0.200);

        return [$competencyWeights, $bloomDistribution, round($targetDifficulty, 3), $minDiscrimination];
    }

    /**
     * Total items to select for the section.
     * Prefers ExamSection::questions_in_section; falls back to sum of
     * blueprint min_questions_count.
     *
     * @param  Collection<int, \App\Domains\ExamEngine\Models\ExamBlueprint>  $blueprints
     */
    private static function itemCount(ExamSection $section, Collection $blueprints): int
    {
        $fromSection = (int) $section->questions_in_section;

        if ($fromSection > 0) {
            return $fromSection;
        }

        return max(1, (int) $blueprints->sum('min_questions_count'));
    }

    /**
     * Most frequent resolution_strategy across blueprints; defaults to 'stratified'.
     *
     * @param  Collection<int, \App\Domains\ExamEngine\Models\ExamBlueprint>  $blueprints
     */
    private static function dominantStrategy(Collection $blueprints): string
    {
        $strategy = $blueprints
            ->countBy('resolution_strategy')
            ->sortDesc()
            ->keys()
            ->first();

        return is_string($strategy) && $strategy !== '' ? $strategy : 'stratified';
    }
}
