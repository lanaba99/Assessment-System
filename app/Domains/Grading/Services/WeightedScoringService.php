<?php

declare(strict_types=1);

namespace App\Domains\Grading\Services;

use App\Domains\Grading\DTOs\WeightedScoreResult;
use App\Domains\Grading\Models\AnswerEvaluation;
use App\Domains\ExamEngine\Models\ExamBlueprint;
use App\Domains\ExamSession\Models\ExamSessionItem;
use Illuminate\Support\Collection;

/**
 * Pure domain service — no constructor dependencies, no database access.
 *
 * Computes blueprint-weighted scores from in-memory collections. Callers are
 * responsible for loading the required data before invoking compute().
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * Join chain (from plan):
 *   AnswerEvaluation.evaluation_metadata['session_item_id']
 *       → ExamSessionItem.section_id
 *           → ExamBlueprint (where exam_id = ? AND section_id = ?)
 *               → min_weight_percentage  (scoring weight)
 *               → competency_id          (competency tracking)
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Normalisation strategy:
 *   weightedPercentage = (weightedSum / totalWeight) × 100
 *
 * Dividing by totalWeight rather than by 100 ensures a 0-100 result even when
 * blueprint weights do not sum to exactly 100. Missing sections (no blueprints)
 * contribute 0 to weightedSum but their weight is still added to totalWeight,
 * so unanswered blueprint sections penalise the candidate's weighted score.
 *
 * Evaluations whose session_item_id cannot be resolved to a blueprint are
 * counted in rawScore but excluded from the weighted calculation and tracked
 * in unmappedEvaluationCount for diagnostics.
 */
class WeightedScoringService
{
    /**
     * @param  Collection<int, AnswerEvaluation>  $evaluations  All evaluations for the session
     * @param  Collection<int, ExamBlueprint>     $blueprints   All blueprints for the exam
     * @param  Collection<int, ExamSessionItem>   $sessionItems All session items for the session
     */
    public function compute(
        Collection $evaluations,
        Collection $blueprints,
        Collection $sessionItems,
    ): WeightedScoreResult {
        // ── Step 1: Build lookup maps (in-memory, O(n)) ────────────────────

        // Map session_item_id → ExamSessionItem for instant lookup
        /** @var Collection<string, ExamSessionItem> */
        $itemMap = $sessionItems->keyBy(fn (ExamSessionItem $i): string => (string) $i->session_item_id);

        // Group blueprints by section_id (exclude unsectioned blueprints)
        // Multiple blueprints per section are possible (one per competency)
        /** @var Collection<string, Collection<int, ExamBlueprint>> */
        $blueprintsBySection = $blueprints
            ->filter(fn (ExamBlueprint $b): bool => $b->section_id !== null)
            ->groupBy(fn (ExamBlueprint $b): string => (string) $b->section_id);

        // ── Step 2: Group evaluations by section_id ─────────────────────────

        /** @var array<string, list<AnswerEvaluation>> */
        $evalsBySection = [];
        $unmappedCount = 0;

        foreach ($evaluations as $eval) {
            $metadata = is_array($eval->evaluation_metadata) ? $eval->evaluation_metadata : [];
            $sessionItemId = $metadata['session_item_id'] ?? null;

            if ($sessionItemId === null) {
                $unmappedCount++;
                continue;
            }

            $item = $itemMap->get((string) $sessionItemId);

            if ($item === null) {
                $unmappedCount++;
                continue;
            }

            $sectionId = (string) $item->section_id;
            $evalsBySection[$sectionId][] = $eval;
        }

        // ── Step 3: Compute flat raw totals (all evaluations, including unmapped) ──

        $rawScore = 0.0;
        $rawMaxScore = 0.0;

        foreach ($evaluations as $eval) {
            $rawScore += (float) $eval->score_awarded;
            $rawMaxScore += (float) $eval->max_score_possible;
        }

        // ── Step 4: Compute per-section weighted contributions ──────────────

        $weightedSum = 0.0;
        $totalWeight = 0.0;

        /** @var array<string, array<string, mixed>> */
        $sectionBreakdown = [];
        /** @var array<string, array<string, mixed>> */
        $competencyBreakdown = [];

        foreach ($blueprintsBySection as $sectionId => $sectionBlueprints) {
            $sectionId = (string) $sectionId;
            $sectionEvals = $evalsBySection[$sectionId] ?? [];

            $sectionAwarded = 0.0;
            $sectionMax = 0.0;
            foreach ($sectionEvals as $eval) {
                $sectionAwarded += (float) $eval->score_awarded;
                $sectionMax += (float) $eval->max_score_possible;
            }

            // section_percentage: 0-1 ratio (0% if no questions delivered)
            $sectionPercentage = $sectionMax > 0.0 ? ($sectionAwarded / $sectionMax) : 0.0;

            // Sum all blueprint weights assigned to this section
            $sectionTotalWeight = (float) $sectionBlueprints->sum(
                fn (ExamBlueprint $b): float => (float) $b->min_weight_percentage
            );

            $sectionContribution = $sectionPercentage * $sectionTotalWeight;
            $weightedSum += $sectionContribution;
            $totalWeight += $sectionTotalWeight;

            $sectionBreakdown[$sectionId] = [
                'section_id' => $sectionId,
                'evaluated_count' => count($sectionEvals),
                'awarded' => round($sectionAwarded, 4),
                'max' => round($sectionMax, 4),
                'section_percentage' => round($sectionPercentage * 100.0, 2),
                'weight' => $sectionTotalWeight,
                'weighted_contribution' => round($sectionContribution, 4),
            ];

            // Accumulate per-competency contributions
            foreach ($sectionBlueprints as $blueprint) {
                $competencyId = (string) $blueprint->competency_id;
                $blueprintWeight = (float) $blueprint->min_weight_percentage;
                $competencyContribution = $sectionPercentage * $blueprintWeight;

                if (! isset($competencyBreakdown[$competencyId])) {
                    $competencyBreakdown[$competencyId] = [
                        'competency_id' => $competencyId,
                        'total_weighted_contribution' => 0.0,
                        'total_weight' => 0.0,
                        'section_count' => 0,
                        'section_ids' => [],
                    ];
                }

                $competencyBreakdown[$competencyId]['total_weighted_contribution'] += $competencyContribution;
                $competencyBreakdown[$competencyId]['total_weight'] += $blueprintWeight;
                $competencyBreakdown[$competencyId]['section_count']++;
                $competencyBreakdown[$competencyId]['section_ids'][] = $sectionId;
            }
        }

        // ── Step 5: Normalise weighted score ────────────────────────────────
        //
        // Divide by totalWeight (not by 100) so the result is a 0-100 percentage
        // regardless of whether blueprint weights sum exactly to 100.
        $weightedPercentage = $totalWeight > 0.0
            ? round(($weightedSum / $totalWeight) * 100.0, 2)
            : 0.0;

        return new WeightedScoreResult(
            rawScore: round($rawScore, 2),
            rawMaxScore: round($rawMaxScore, 2),
            weightedPercentage: $weightedPercentage,
            totalWeight: round($totalWeight, 2),
            unmappedEvaluationCount: $unmappedCount,
            sectionBreakdown: $sectionBreakdown,
            competencyBreakdown: $competencyBreakdown,
        );
    }
}
