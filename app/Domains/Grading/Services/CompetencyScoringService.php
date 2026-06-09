<?php

declare(strict_types=1);

namespace App\Domains\Grading\Services;

use App\Domains\Grading\DTOs\WeightedScoreResult;
use App\Domains\Grading\Repositories\CompetencyScoreRepository;

/**
 * Persists per-competency scores derived from a WeightedScoreResult.
 *
 * Called by AssessmentFinalizationServiceImpl after the Grade row is upserted,
 * inside the same DB transaction. Only called when blueprints were matched
 * (WeightedScoreResult::hasBlueprints() === true).
 *
 * Score representation:
 *   score_achieved  — candidate's percentage (0-100) on this competency's questions
 *   score_target    — 70.0 default proficiency target (configurable in a future phase)
 *   score_maximum   — total blueprint weight allocated to this competency
 *   gap_percentage  — score_achieved − score_target (positive = above, negative = below)
 *
 * Proficiency levels (aligned with common assessment frameworks):
 *   ≥ 80%  → 5 (Expert)
 *   ≥ 60%  → 4 (Proficient)
 *   ≥ 40%  → 3 (Competent)
 *   ≥ 20%  → 2 (Developing)
 *   <  20%  → 1 (Novice)
 */
class CompetencyScoringService
{
    private const DEFAULT_TARGET_PERCENTAGE = 70.0;

    private const ON_TARGET_TOLERANCE = 5.0;

    public function __construct(
        private readonly CompetencyScoreRepository $repository,
    ) {
    }

    /**
     * Compute and persist a CompetencyScore row for each competency in the result.
     * This method is idempotent: calling it again for the same session overwrites
     * the previous row (same upsert pattern as Grade/AssessmentResult).
     */
    public function computeAndPersist(
        string $tenantId,
        string $sessionId,
        string $candidateId,
        WeightedScoreResult $result,
    ): void {
        foreach ($result->competencyBreakdown as $competencyId => $data) {
            $totalWeight = (float) $data['total_weight'];
            $totalContribution = (float) $data['total_weighted_contribution'];

            // score_achieved: the candidate's normalised percentage for this competency
            $scoreAchieved = $totalWeight > 0.0
                ? round(($totalContribution / $totalWeight) * 100.0, 2)
                : 0.0;

            $proficiencyLevel = $this->proficiencyLevel($scoreAchieved);

            $gapPercentage = round($scoreAchieved - self::DEFAULT_TARGET_PERCENTAGE, 2);

            $gapStatus = match (true) {
                $gapPercentage >= self::ON_TARGET_TOLERANCE => 'above_target',
                $gapPercentage <= -self::ON_TARGET_TOLERANCE => 'below_target',
                default => 'on_target',
            };

            $this->repository->upsertForSession(
                tenantId: $tenantId,
                sessionId: $sessionId,
                competencyId: (string) $competencyId,
                candidateId: $candidateId,
                attributes: [
                    'score_achieved' => $scoreAchieved,
                    'score_target' => self::DEFAULT_TARGET_PERCENTAGE,
                    // score_maximum stores the total blueprint weight so the reader
                    // knows how significant this competency is relative to the exam.
                    'score_maximum' => $totalWeight,
                    'proficiency_level_achieved' => $proficiencyLevel,
                    'gap_percentage' => $gapPercentage,
                    'gap_status' => $gapStatus,
                    'score_metadata' => [
                        'total_weighted_contribution' => $totalContribution,
                        'total_weight' => $totalWeight,
                        'section_count' => (int) $data['section_count'],
                        'section_ids' => $data['section_ids'],
                    ],
                ],
            );
        }
    }

    private function proficiencyLevel(float $scorePercentage): int
    {
        return match (true) {
            $scorePercentage >= 80.0 => 5, // Expert
            $scorePercentage >= 60.0 => 4, // Proficient
            $scorePercentage >= 40.0 => 3, // Competent
            $scorePercentage >= 20.0 => 2, // Developing
            default => 1,                  // Novice
        };
    }
}
