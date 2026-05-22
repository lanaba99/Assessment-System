<?php

declare(strict_types=1);

namespace App\Domains\Grading\Repositories;

use App\Domains\Grading\DTOs\AssessmentSummary;
use App\Domains\Grading\Models\Grade;

class GradeRepository
{
    public function __construct(
        private readonly Grade $model,
    ) {
    }

    public function findBySession(string $sessionId): ?Grade
    {
        return $this->model
            ->newQuery()
            ->where('session_id', $sessionId)
            ->first();
    }

    public function upsertFromSummary(AssessmentSummary $summary): Grade
    {
        $now = now();

        $attributes = [
            'session_id' => $summary->sessionId,
            'candidate_user_id' => $summary->candidateId,
            'exam_id' => $summary->examId,
            'tenant_id' => $summary->tenantId,
            'raw_score' => $summary->rawScore,
            'weighted_score' => $summary->rawScore,
            'normalized_score' => $summary->percentage,
            'final_score' => $summary->percentage,
            'grade_letter' => $summary->gradeLetter,
            'is_passing_grade' => $summary->isPassing,
            'requires_second_marking' => ! $summary->isFinal,
            'is_final_grade' => $summary->isFinal,
            'grading_metadata' => [
                'total_evaluations' => $summary->totalEvaluations,
                'pending_evaluations' => $summary->pendingEvaluations,
                'correct_count' => $summary->correctCount,
                'incorrect_count' => $summary->incorrectCount,
                'max_score' => $summary->maxScore,
                'breakdown' => $summary->breakdown,
            ],
            'graded_at' => $now,
            'finalized_at' => $summary->isFinal ? $now : null,
        ];

        $existing = $this->findBySession($summary->sessionId);

        if ($existing !== null) {
            $existing->forceFill($attributes)->save();

            return $existing;
        }

        $attributes['created_at'] = $now;

        return $this->model->newQuery()->create($attributes);
    }
}
