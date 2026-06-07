<?php

declare(strict_types=1);

namespace App\Http\Requests\ExamSession;

use App\Domains\ExamSession\DTOs\SubmitResponseCommand;
use Illuminate\Foundation\Http\FormRequest;

class SubmitResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership is enforced by ExamSessionPolicy::participate() in the controller.
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
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

    /**
     * Builds the command from validated input. tenantId and candidateId are
     * derived from context — they are never accepted from the request body.
     */
    public function toCommand(string $tenantId, string $sessionId, string $candidateId): SubmitResponseCommand
    {
        $validated = $this->validated();

        return new SubmitResponseCommand(
            tenantId: $tenantId,
            sessionId: $sessionId,
            sessionItemId: (string) $validated['session_item_id'],
            candidateId: $candidateId,
            responseType: (string) $validated['response_type'],
            responseData: $validated['response_data'] ?? null,
            responseText: isset($validated['response_text']) ? (string) $validated['response_text'] : null,
            selectedOptions: $validated['selected_options'] ?? null,
            fileUploadUrl: isset($validated['file_upload_url']) ? (string) $validated['file_upload_url'] : null,
            timeSpentSeconds: isset($validated['time_spent_seconds']) ? (int) $validated['time_spent_seconds'] : null,
            timeElapsedFromStartSeconds: isset($validated['time_elapsed_from_start_seconds'])
                ? (int) $validated['time_elapsed_from_start_seconds']
                : null,
            isFlaggedForReview: (bool) ($validated['is_flagged_for_review'] ?? false),
            expectedItemVersionLock: isset($validated['expected_item_version_lock'])
                ? (int) $validated['expected_item_version_lock']
                : null,
        );
    }
}
