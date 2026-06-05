<?php

declare(strict_types=1);

namespace App\Http\Resources\ExamEngine;

use App\Domains\ExamEngine\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Exam
 */
class ExamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->exam_id,
            'tenant_id' => (string) $this->tenant_id,
            'created_by_user_id' => (string) $this->created_by_user_id,
            'exam_name' => (string) $this->exam_name,
            'exam_code' => (string) $this->exam_code,
            'exam_description' => $this->exam_description,
            'exam_type' => (string) $this->exam_type,
            'assessment_mode' => $this->assessment_mode,
            'total_questions' => (int) $this->total_questions,
            'total_duration_minutes' => (int) $this->total_duration_minutes,
            'pass_mark_percentage' => (float) $this->pass_mark_percentage,
            'difficulty_tier_level' => $this->difficulty_tier_level !== null ? (int) $this->difficulty_tier_level : null,
            'is_adaptive_exam' => (bool) $this->is_adaptive_exam,
            'is_randomized' => (bool) $this->is_randomized,
            'allow_review_after_submit' => (bool) $this->allow_review_after_submit,
            'allow_flagging_for_review' => (bool) $this->allow_flagging_for_review,
            'timer_visible_to_candidate' => (bool) $this->timer_visible_to_candidate,
            'show_correct_answers_after' => (bool) $this->show_correct_answers_after,
            'security_protocols' => $this->security_protocols,
            'exam_metadata' => $this->exam_metadata,
            'exam_status' => $this->exam_status->value,
            'is_published' => (bool) $this->is_published,
            'published_at' => $this->published_at?->toIso8601String(),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
