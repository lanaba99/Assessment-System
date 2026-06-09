<?php

declare(strict_types=1);

namespace App\Domains\Grading\DTOs;

/**
 * Produced by PenaltyApplicationService::computeWithAudit().
 *
 * Carries both the scalar deduction (for score clamping) and the audit trail
 * (for Grade.grading_metadata persistence) so a single read pass suffices.
 *
 * totalDeduction is expressed in the same 0-100 percentage-point scale as the
 * Grade.normalized_score column: subtracting it directly from the weighted
 * percentage gives the candidate's final score.
 *
 * sanctionsApplied shape (one entry per 'penalty' type sanction):
 *   [
 *     'sanction_id'    => string (UUID),
 *     'penalty_rule_id'=> string (UUID),
 *     'amount'         => float  (percentage points deducted),
 *     'reason'         => string,
 *     'applied_at'     => string|null (ISO 8601),
 *   ]
 */
final readonly class PenaltyDeductionResult
{
    /**
     * @param  array<int, array<string, mixed>>  $sanctionsApplied
     */
    public function __construct(
        public float $totalDeduction,
        public array $sanctionsApplied,
    ) {
    }

    public function hasDeductions(): bool
    {
        return $this->totalDeduction > 0.0;
    }
}
