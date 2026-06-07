<?php

declare(strict_types=1);

namespace App\Http\Requests\Proctoring;

use App\Domains\Proctoring\DTOs\LogProctorEventCommand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LogProctorEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Session ownership and state validity are enforced inside ProctoringService.
        // Any authenticated user with a valid Sanctum token can reach this endpoint;
        // the service distinguishes candidate-sourced from proctor-sourced events.
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event_type' => ['required', 'string', 'max:100'],
            'event_timestamp' => ['required', 'date'],
            'event_category' => ['nullable', 'string', 'max:100'],
            'event_payload' => ['nullable', 'array'],
            'severity_level' => ['nullable', 'string', Rule::in(['info', 'warning', 'critical'])],
            'detection_confidence_score' => ['nullable', 'numeric', 'between:0,1'],
            'screenshot_url' => ['nullable', 'string', 'max:2048'],
            'video_segment_url' => ['nullable', 'string', 'max:2048'],
            'detection_parameters' => ['nullable', 'array'],
        ];
    }

    public function toCommand(string $tenantId, string $sessionId): LogProctorEventCommand
    {
        $validated = $this->validated();

        return new LogProctorEventCommand(
            tenantId: $tenantId,
            sessionId: $sessionId,
            actorId: (string) $this->user()->id,
            eventType: (string) $validated['event_type'],
            eventTimestamp: (string) $validated['event_timestamp'],
            eventCategory: isset($validated['event_category']) ? (string) $validated['event_category'] : null,
            eventPayload: $validated['event_payload'] ?? null,
            severityLevel: isset($validated['severity_level']) ? (string) $validated['severity_level'] : 'info',
            detectionConfidenceScore: isset($validated['detection_confidence_score'])
                ? (float) $validated['detection_confidence_score']
                : null,
            screenshotUrl: isset($validated['screenshot_url']) ? (string) $validated['screenshot_url'] : null,
            videoSegmentUrl: isset($validated['video_segment_url']) ? (string) $validated['video_segment_url'] : null,
            detectionParameters: $validated['detection_parameters'] ?? null,
        );
    }
}
