<?php

declare(strict_types=1);

namespace App\Domains\Grading\Repositories;

use App\Domains\Grading\DTOs\AssessmentSummary;
use App\Domains\Grading\Models\AssessmentResult;

/**
 * Tenant isolation: explicit where('tenant_id') on every query.
 * AssessmentResult uses AutoFillsTenantId (no global scope), so this
 * repository is the primary isolation layer for result data.
 */
class AssessmentResultRepository
{
    public function __construct(
        private readonly AssessmentResult $model,
    ) {
    }

    public function findBySession(string $tenantId, string $sessionId): ?AssessmentResult
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('session_id', $sessionId)
            ->first();
    }

    public function upsertFromSummary(AssessmentSummary $summary): AssessmentResult
    {
        $now = now();

        $attributes = [
            'candidate_user_id' => $summary->candidateId,
            'session_id' => $summary->sessionId,
            'exam_id' => $summary->examId,
            'tenant_id' => $summary->tenantId,
            'result_status' => $summary->resultStatus(),
            'result_calculated_at' => $now,
            'result_metadata' => [
                'raw_score' => $summary->rawScore,
                'max_score' => $summary->maxScore,
                'percentage' => $summary->percentage,
                'grade_letter' => $summary->gradeLetter,
                'is_passing' => $summary->isPassing,
                'total_evaluations' => $summary->totalEvaluations,
                'pending_evaluations' => $summary->pendingEvaluations,
                'correct_count' => $summary->correctCount,
                'incorrect_count' => $summary->incorrectCount,
            ],
        ];

        // Pass tenantId to the scoped read so the upsert only matches
        // the correct tenant's row even when session UUIDs are non-unique.
        $existing = $this->findBySession($summary->tenantId, $summary->sessionId);

        if ($existing !== null) {
            $existing->forceFill($attributes)->save();

            return $existing;
        }

        $attributes['publication_status'] = 'unpublished';
        $attributes['created_at'] = $now;

        return $this->model->newQuery()->create($attributes);
    }
}
