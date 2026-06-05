<?php

declare(strict_types=1);

namespace App\Http\Requests\ExamEngine;

use App\Domains\ExamEngine\DTOs\CreateExamCommand;
use App\Domains\ExamEngine\Models\Exam;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('create', Exam::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'exam_name' => ['required', 'string', 'max:255'],
            'exam_code' => ['required', 'string', 'max:50', 'unique:exams,exam_code'],
            'exam_description' => ['nullable', 'string'],
            'exam_type' => ['required', 'string', Rule::in([
                'certification', 'placement', 'training', 'evaluation', 'practice',
            ])],
            'assessment_mode' => ['nullable', 'string', Rule::in([
                'online', 'hybrid', 'paper',
            ])],
            'total_questions' => ['required', 'integer', 'min:1', 'max:500'],
            'total_duration_minutes' => ['required', 'integer', 'min:1', 'max:480'],
            'pass_mark_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'difficulty_tier_level' => ['nullable', 'integer', 'min:1', 'max:5'],
            'is_adaptive_exam' => ['nullable', 'boolean'],
            'is_randomized' => ['nullable', 'boolean'],
            'allow_review_after_submit' => ['nullable', 'boolean'],
            'allow_flagging_for_review' => ['nullable', 'boolean'],
            'timer_visible_to_candidate' => ['nullable', 'boolean'],
            'show_correct_answers_after' => ['nullable', 'boolean'],
            'security_protocols' => ['nullable', 'array'],
            'exam_metadata' => ['nullable', 'array'],
        ];
    }

    public function toCommand(string $tenantId, string $createdByUserId): CreateExamCommand
    {
        $validated = $this->validated();

        return new CreateExamCommand(
            tenantId: $tenantId,
            createdByUserId: $createdByUserId,
            examName: (string) $validated['exam_name'],
            examCode: (string) $validated['exam_code'],
            examType: (string) $validated['exam_type'],
            totalQuestions: (int) $validated['total_questions'],
            totalDurationMinutes: (int) $validated['total_duration_minutes'],
            examDescription: isset($validated['exam_description']) ? (string) $validated['exam_description'] : null,
            assessmentMode: isset($validated['assessment_mode']) ? (string) $validated['assessment_mode'] : null,
            passMarkPercentage: isset($validated['pass_mark_percentage']) ? (float) $validated['pass_mark_percentage'] : 60.0,
            difficultyTierLevel: isset($validated['difficulty_tier_level']) ? (int) $validated['difficulty_tier_level'] : null,
            isAdaptiveExam: (bool) ($validated['is_adaptive_exam'] ?? false),
            isRandomized: (bool) ($validated['is_randomized'] ?? false),
            allowReviewAfterSubmit: (bool) ($validated['allow_review_after_submit'] ?? false),
            allowFlaggingForReview: (bool) ($validated['allow_flagging_for_review'] ?? true),
            timerVisibleToCandidate: (bool) ($validated['timer_visible_to_candidate'] ?? true),
            showCorrectAnswersAfter: (bool) ($validated['show_correct_answers_after'] ?? false),
            securityProtocols: $validated['security_protocols'] ?? null,
            examMetadata: $validated['exam_metadata'] ?? null,
        );
    }
}
