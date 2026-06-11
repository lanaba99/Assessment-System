<?php

declare(strict_types=1);

namespace App\Domains\Grading\Services;

use App\Domains\Grading\Models\Grade;
use Illuminate\Support\Facades\DB;

class FinalGradeProcessingService
{
    public function __construct(
        private readonly Grade $grades,
        private readonly PenaltyApplicationService $penalties,
        private readonly CompetencyAggregationService $competencies,
    ) {
    }

    public function process(string $tenantId, string $sessionId): void
    {
        DB::transaction(function () use ($tenantId, $sessionId): void {
            $grade = $this->grades
                ->newQuery()
                ->where('tenant_id', $tenantId)
                ->where('session_id', $sessionId)
                ->lockForUpdate()
                ->first();

            if ($grade === null || ! $grade->is_final_grade) {
                return;
            }

            $penaltyResult = $this->penalties->computeWithAudit($tenantId, $sessionId);
            $metadata = is_array($grade->grading_metadata) ? $grade->grading_metadata : [];
            $prePenaltyScore = (float) ($grade->normalized_score ?? $grade->final_score ?? 0.0);
            $finalScore = round(max(0.0, $prePenaltyScore - $penaltyResult->totalDeduction), 2);
            $pendingPenaltyProcessing = $this->penalties->hasPendingProcessing($tenantId, $sessionId);

            $metadata['penalty_deduction'] = $penaltyResult->totalDeduction;
            $metadata['sanctions_applied'] = $penaltyResult->sanctionsApplied;
            $metadata['penalty_processing_status'] = $pendingPenaltyProcessing ? 'pending' : 'completed';
            $metadata['penalty_inputs_present'] = $this->penalties->hasPenaltyInputs($tenantId, $sessionId);
            $metadata['final_grade_processed_at'] = now()->toIso8601String();

            $grade->forceFill([
                'final_score' => $finalScore,
                'grading_metadata' => $metadata,
            ])->save();
        });

        $grade = $this->grades
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('session_id', $sessionId)
            ->first();

        if ($grade !== null && $grade->is_final_grade) {
            $this->competencies->aggregateForFinalGrade(
                tenantId: $tenantId,
                sessionId: $sessionId,
                candidateId: (string) $grade->candidate_user_id,
            );
        }
    }
}
