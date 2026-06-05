<?php

declare(strict_types=1);

namespace App\Http\Requests\ExamEngine;

use App\Domains\ExamEngine\DTOs\UpdateExamCommand;
use App\Domains\ExamEngine\Models\Exam;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $tenantId = (string) tenant()->getKey();
        $exam = app(\App\Domains\ExamEngine\Repositories\ExamRepository::class)
            ->findById($tenantId, (string) $this->route('examId'));

        if ($exam === null) {
            abort(404, 'Exam not found.');
        }

        return $user->can('update', $exam);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'exam_name' => ['sometimes', 'string', 'max:255'],
            'exam_code' => [
                'sometimes', 'string', 'max:50',
                Rule::unique('exams', 'exam_code')
                    ->ignore($this->route('examId'), 'exam_id'),
            ],
            'exam_description' => ['sometimes', 'nullable', 'string'],
            'exam_type' => ['sometimes', 'string', Rule::in([
                'certification', 'placement', 'training', 'evaluation', 'practice',
            ])],
            'assessment_mode' => ['sometimes', 'nullable', 'string', Rule::in([
                'online', 'hybrid', 'paper',
            ])],
            'total_questions' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'total_duration_minutes' => ['sometimes', 'integer', 'min:1', 'max:480'],
            'pass_mark_percentage' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'difficulty_tier_level' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'is_adaptive_exam' => ['sometimes', 'boolean'],
            'is_randomized' => ['sometimes', 'boolean'],
            'allow_review_after_submit' => ['sometimes', 'boolean'],
            'allow_flagging_for_review' => ['sometimes', 'boolean'],
            'timer_visible_to_candidate' => ['sometimes', 'boolean'],
            'show_correct_answers_after' => ['sometimes', 'boolean'],
            'security_protocols' => ['sometimes', 'nullable', 'array'],
            'exam_metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function toCommand(): UpdateExamCommand
    {
        $validated = $this->validated();

        return new UpdateExamCommand(
            examName: isset($validated['exam_name']) ? (string) $validated['exam_name'] : null,
            examCode: isset($validated['exam_code']) ? (string) $validated['exam_code'] : null,
            examDescription: array_key_exists('exam_description', $validated) ? ($validated['exam_description'] !== null ? (string) $validated['exam_description'] : null) : null,
            examType: isset($validated['exam_type']) ? (string) $validated['exam_type'] : null,
            assessmentMode: array_key_exists('assessment_mode', $validated) ? ($validated['assessment_mode'] !== null ? (string) $validated['assessment_mode'] : null) : null,
            totalQuestions: isset($validated['total_questions']) ? (int) $validated['total_questions'] : null,
            totalDurationMinutes: isset($validated['total_duration_minutes']) ? (int) $validated['total_duration_minutes'] : null,
            passMarkPercentage: isset($validated['pass_mark_percentage']) ? (float) $validated['pass_mark_percentage'] : null,
            difficultyTierLevel: isset($validated['difficulty_tier_level']) ? (int) $validated['difficulty_tier_level'] : null,
            isAdaptiveExam: isset($validated['is_adaptive_exam']) ? (bool) $validated['is_adaptive_exam'] : null,
            isRandomized: isset($validated['is_randomized']) ? (bool) $validated['is_randomized'] : null,
            allowReviewAfterSubmit: isset($validated['allow_review_after_submit']) ? (bool) $validated['allow_review_after_submit'] : null,
            allowFlaggingForReview: isset($validated['allow_flagging_for_review']) ? (bool) $validated['allow_flagging_for_review'] : null,
            timerVisibleToCandidate: isset($validated['timer_visible_to_candidate']) ? (bool) $validated['timer_visible_to_candidate'] : null,
            showCorrectAnswersAfter: isset($validated['show_correct_answers_after']) ? (bool) $validated['show_correct_answers_after'] : null,
            securityProtocols: array_key_exists('security_protocols', $validated) ? $validated['security_protocols'] : null,
            examMetadata: array_key_exists('exam_metadata', $validated) ? $validated['exam_metadata'] : null,
        );
    }
}
