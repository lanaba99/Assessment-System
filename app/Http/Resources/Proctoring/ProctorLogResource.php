<?php

declare(strict_types=1);

namespace App\Http\Resources\Proctoring;

use App\Domains\Proctoring\Models\ProctorLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProctorLog
 */
class ProctorLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->event_id,
            'session_id' => (string) $this->session_id,
            'tenant_id' => (string) $this->tenant_id,
            'candidate_user_id' => (string) $this->candidate_user_id,
            'reviewing_proctor_id' => $this->reviewing_proctor_id !== null
                ? (string) $this->reviewing_proctor_id
                : null,
            'event_type' => (string) $this->event_type,
            'event_category' => $this->event_category,
            'event_timestamp' => $this->event_timestamp?->toIso8601String(),
            'event_payload' => $this->event_payload,
            'severity_level' => (string) $this->severity_level,
            'detection_confidence_score' => $this->detection_confidence_score !== null
                ? (float) $this->detection_confidence_score
                : null,
            'screenshot_url' => $this->screenshot_url,
            'video_segment_url' => $this->video_segment_url,
            'requires_investigation' => (bool) $this->requires_investigation,
            'is_escalated' => (bool) $this->is_escalated,
            'investigation_status' => (string) $this->investigation_status,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
