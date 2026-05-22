<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domains\ExamSession\DTOs\SubmitResponseCommand;
use Illuminate\Foundation\Http\FormRequest;

class SubmitResponseRequest extends FormRequest
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
            'tenant_id' => ['required', 'uuid'],
            'candidate_id' => ['required', 'uuid'],
            'session_item_id' => ['required', 'uuid'],
            'response_type' => ['required', 'string', 'max:64'],
            'response_data' => ['nullable', 'array'],
            'response_text' => ['nullable', 'string'],
            'selected_options' => ['nullable', 'array'],
            'file_upload_url' => ['nullable', 'string', 'max:1024'],
            'time_spent_seconds' => ['nullable', 'integer', 'min:0'],
            'time_elapsed_from_start_seconds' => ['nullable', 'integer', 'min:0'],
            'is_flagged_for_review' => ['sometimes', 'boolean'],
            'expected_item_version_lock' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function toCommand(string $sessionId): SubmitResponseCommand
    {
        $validated = $this->validated();

        return new SubmitResponseCommand(
            tenantId: $validated['tenant_id'],
            sessionId: $sessionId,
            sessionItemId: $validated['session_item_id'],
            candidateId: $validated['candidate_id'],
            responseType: $validated['response_type'],
            responseData: $validated['response_data'] ?? null,
            responseText: $validated['response_text'] ?? null,
            selectedOptions: $validated['selected_options'] ?? null,
            fileUploadUrl: $validated['file_upload_url'] ?? null,
            timeSpentSeconds: $validated['time_spent_seconds'] ?? null,
            timeElapsedFromStartSeconds: $validated['time_elapsed_from_start_seconds'] ?? null,
            isFlaggedForReview: (bool) ($validated['is_flagged_for_review'] ?? false),
            expectedItemVersionLock: $validated['expected_item_version_lock'] ?? null,
        );
    }
}
