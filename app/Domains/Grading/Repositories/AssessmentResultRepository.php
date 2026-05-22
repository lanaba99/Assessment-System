<?php

declare(strict_types=1);

namespace App\Domains\Grading\Repositories;

use App\Domains\Grading\DTOs\AssessmentSummary;
use App\Domains\Grading\Models\AssessmentResult;

class AssessmentResultRepository
{
    public function __construct(
        private readonly AssessmentResult $model,
    ) {
    }

    public function findBySession(string $sessionId): ?AssessmentResult
    {
        return $this->model
            ->newQuery()
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

        $existing = $this->findBySession($summary->sessionId);

        if ($existing !== null) {
            $existing->forceFill($attributes)->save();

            return $existing;
        }

        $attributes['publication_status'] = 'unpublished';
        $attributes['created_at'] = $now;

        return $this->model->newQuery()->create($attributes);
    }
}
