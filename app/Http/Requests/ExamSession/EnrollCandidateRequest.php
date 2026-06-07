<?php

declare(strict_types=1);

namespace App\Http\Requests\ExamSession;

use App\Domains\ExamSession\DTOs\EnrollCandidateCommand;
use App\Domains\ExamSession\Models\ExamCandidateEligible;
use Illuminate\Foundation\Http\FormRequest;

class EnrollCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->user()->can('manage-enrollments', ExamCandidateEligible::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'candidate_user_id' => ['required', 'uuid', 'exists:users,id'],
            'cohort_id' => ['nullable', 'uuid'],
            'start_window_date' => ['nullable', 'date'],
            'end_window_date' => ['nullable', 'date', 'after_or_equal:start_window_date'],
            'max_attempts_allowed' => ['nullable', 'integer', 'min:1', 'max:10'],
            'enrollment_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function toCommand(string $tenantId, string $examId): EnrollCandidateCommand
    {
        $validated = $this->validated();

        return new EnrollCandidateCommand(
            tenantId: $tenantId,
            examId: $examId,
            candidateUserId: (string) $validated['candidate_user_id'],
            cohortId: isset($validated['cohort_id']) ? (string) $validated['cohort_id'] : null,
            startWindowDate: isset($validated['start_window_date']) ? (string) $validated['start_window_date'] : null,
            endWindowDate: isset($validated['end_window_date']) ? (string) $validated['end_window_date'] : null,
            maxAttemptsAllowed: (int) ($validated['max_attempts_allowed'] ?? 1),
            enrollmentNotes: isset($validated['enrollment_notes']) ? (string) $validated['enrollment_notes'] : null,
        );
    }
}
