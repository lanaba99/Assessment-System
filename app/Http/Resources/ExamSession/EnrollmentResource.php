<?php

declare(strict_types=1);

namespace App\Http\Resources\ExamSession;

use App\Domains\ExamSession\Models\ExamCandidateEligible;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExamCandidateEligible
 */
class EnrollmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->enrollment_id,
            'exam_id' => (string) $this->exam_id,
            'candidate_user_id' => (string) $this->candidate_user_id,
            'tenant_id' => (string) $this->tenant_id,
            'cohort_id' => $this->cohort_id !== null ? (string) $this->cohort_id : null,
            'enrollment_status' => (string) $this->enrollment_status,
            'enrollment_date' => $this->enrollment_date?->toIso8601String(),
            'start_window_date' => $this->start_window_date?->toIso8601String(),
            'end_window_date' => $this->end_window_date?->toIso8601String(),
            'can_retake_exam' => (bool) $this->can_retake_exam,
            'max_attempts_allowed' => (int) $this->max_attempts_allowed,
            'attempts_used' => (int) $this->attempts_used,
            'attempts_remaining' => (int) $this->attempts_remaining,
            'highest_score_achieved' => $this->highest_score_achieved !== null
                ? (float) $this->highest_score_achieved
                : null,
            'highest_score_status' => $this->highest_score_status,
            'enrollment_notes' => $this->enrollment_notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
