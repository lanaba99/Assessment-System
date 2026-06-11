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

    public function findPublishedBySession(string $tenantId, string $sessionId): ?AssessmentResult
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('session_id', $sessionId)
            ->where('publication_status', 'published')
            ->first();
    }

    public function findPublishedForCandidateSession(
        string $tenantId,
        string $sessionId,
        string $candidateId,
    ): ?AssessmentResult {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('session_id', $sessionId)
            ->where('candidate_user_id', $candidateId)
            ->where('publication_status', 'published')
            ->first();
    }

    public function lockBySessionForPublication(string $tenantId, string $sessionId): ?AssessmentResult
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('session_id', $sessionId)
            ->lockForUpdate()
            ->first();
    }

    public function publish(AssessmentResult $result, ?string $publishedByUserId = null): AssessmentResult
    {
        if ($result->publication_status === 'published') {
            return $result;
        }

        $publishedAt = now();
        $metadata = is_array($result->result_metadata) ? $result->result_metadata : [];
        $metadata['publication'] = array_filter([
            'published_by_user_id' => $publishedByUserId,
            'published_at' => $publishedAt->toISOString(),
        ], static fn (mixed $value): bool => $value !== null);

        $result->forceFill([
            'publication_status' => 'published',
            'published_at' => $publishedAt,
            'result_metadata' => $metadata,
        ])->save();

        return $result;
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
