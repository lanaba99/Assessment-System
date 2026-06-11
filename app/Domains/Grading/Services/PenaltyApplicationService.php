<?php

declare(strict_types=1);

namespace App\Domains\Grading\Services;

use App\Domains\Grading\DTOs\PenaltyDeductionResult;
use App\Domains\Penalties\Repositories\PenaltySanctionRepository;
use App\Domains\Proctoring\Models\ProctorLog;

/**
 * Read-only service: resolves penalty deductions for a graded session.
 *
 * Only sanctions with sanction_type = 'penalty' contribute to the deduction.
 * Other types ('warning', 'disqualification', etc.) are intentionally excluded —
 * they represent administrative actions, not score adjustments.
 *
 * The sanction_amount is treated as a percentage-point deduction (same 0-100 scale
 * as Grade.normalized_score). Phase E can extend this to support amount_type flags
 * (points vs percentage) if the Rules domain ever emits percentage-based penalties.
 *
 * This service never writes to the database.
 * The Penalties domain owns all sanction writes; this service is a read projection.
 */
class PenaltyApplicationService
{
    private const DEDUCTIBLE_SANCTION_TYPE = 'penalty';

    public function __construct(
        private readonly PenaltySanctionRepository $sanctions,
        private readonly ProctorLog $proctorLogs,
    ) {
    }

    /**
     * Return the total percentage-point deduction for the session.
     * Zero when no 'penalty' sanctions exist.
     *
     * This is the interface required by the spec. Internally it delegates to
     * computeWithAudit() so the DB is only read once per finalization call.
     */
    public function computeDeduction(string $tenantId, string $sessionId): float
    {
        return $this->computeWithAudit($tenantId, $sessionId)->totalDeduction;
    }

    /**
     * Return the deduction AND the full audit trail in a single repository read.
     * Used by AssessmentFinalizationServiceImpl so it can write the audit trail
     * into Grade.grading_metadata without a second query.
     */
    public function computeWithAudit(string $tenantId, string $sessionId): PenaltyDeductionResult
    {
        $allSanctions = $this->sanctions->findForSession($tenantId, $sessionId);

        $deductible = $allSanctions->filter(
            fn ($s): bool => (string) $s->sanction_type === self::DEDUCTIBLE_SANCTION_TYPE
        );

        $totalDeduction = (float) $deductible->sum('sanction_amount');

        $audit = $deductible->map(fn ($s): array => [
            'sanction_id' => (string) $s->sanction_id,
            'penalty_rule_id' => (string) $s->penalty_rule_id,
            'amount' => (float) $s->sanction_amount,
            'reason' => (string) $s->sanction_reason,
            'applied_at' => $s->sanction_applied_at?->toIso8601String(),
        ])->values()->all();

        return new PenaltyDeductionResult(
            totalDeduction: round($totalDeduction, 4),
            sanctionsApplied: $audit,
        );
    }

    public function hasPenaltyInputs(string $tenantId, string $sessionId): bool
    {
        return $this->sanctions->findForSession($tenantId, $sessionId)->isNotEmpty()
            || $this->proctorLogs->newQuery()
                ->where('tenant_id', $tenantId)
                ->where('session_id', $sessionId)
                ->exists();
    }

    public function hasPendingProcessing(string $tenantId, string $sessionId): bool
    {
        return $this->proctorLogs
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('session_id', $sessionId)
            ->where(function ($query): void {
                $query
                    ->where(function ($q): void {
                        $q->where('requires_investigation', true)
                            ->whereNotIn('investigation_status', [
                                'closed',
                                'resolved',
                                'dismissed',
                                'cleared',
                                'no_action',
                            ]);
                    })
                    ->orWhere(function ($q): void {
                        $q->where('is_escalated', true)
                            ->whereNotIn('investigation_status', [
                                'closed',
                                'resolved',
                                'dismissed',
                                'cleared',
                                'no_action',
                            ]);
                    });
            })
            ->exists();
    }
}
