<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domains\ExamEngine\DTOs\CreateExamCommand;
use Illuminate\Foundation\Http\FormRequest;

class CreateExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'created_by_user_id' => ['required', 'uuid'],
            'exam_name' => ['required', 'string', 'max:255'],
            'exam_code' => ['required', 'string', 'max:64'],
            'exam_type' => ['required', 'string', 'max:64'],
            'total_questions' => ['required', 'integer', 'min:1'],
            'total_duration_minutes' => ['required', 'integer', 'min:1'],

            'exam_description' => ['nullable', 'string'],
            'assessment_mode' => ['nullable', 'string', 'max:64'],
            'pass_mark_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'difficulty_tier_level' => ['nullable', 'integer', 'min:1'],

            'is_adaptive_exam' => ['sometimes', 'boolean'],
            'is_randomized' => ['sometimes', 'boolean'],
            'allow_review_after_submit' => ['sometimes', 'boolean'],
            'allow_flagging_for_review' => ['sometimes', 'boolean'],
            'timer_visible_to_candidate' => ['sometimes', 'boolean'],
            'show_correct_answers_after' => ['sometimes', 'boolean'],

            'security_protocols' => ['nullable', 'array'],
            'exam_metadata' => ['nullable', 'array'],
        ];
    }

    public function toCommand(): CreateExamCommand
    {
        $v = $this->validated();

        return new CreateExamCommand(
            tenantId: (string) tenant()->getKey(),
            createdByUserId: (string) $v['created_by_user_id'],
            examName: (string) $v['exam_name'],
            examCode: (string) $v['exam_code'],
            examType: (string) $v['exam_type'],
            totalQuestions: (int) $v['total_questions'],
            totalDurationMinutes: (int) $v['total_duration_minutes'],
            examDescription: $v['exam_description'] ?? null,
            assessmentMode: $v['assessment_mode'] ?? null,
            passMarkPercentage: isset($v['pass_mark_percentage']) ? (float) $v['pass_mark_percentage'] : 60.0,
            difficultyTierLevel: isset($v['difficulty_tier_level']) ? (int) $v['difficulty_tier_level'] : null,
            isAdaptiveExam: (bool) ($v['is_adaptive_exam'] ?? false),
            isRandomized: (bool) ($v['is_randomized'] ?? false),
            allowReviewAfterSubmit: (bool) ($v['allow_review_after_submit'] ?? false),
            allowFlaggingForReview: (bool) ($v['allow_flagging_for_review'] ?? true),
            timerVisibleToCandidate: (bool) ($v['timer_visible_to_candidate'] ?? true),
            showCorrectAnswersAfter: (bool) ($v['show_correct_answers_after'] ?? false),
            securityProtocols: $v['security_protocols'] ?? null,
            examMetadata: $v['exam_metadata'] ?? null,
        );
    }
}
