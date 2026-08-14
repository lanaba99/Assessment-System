<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\ExamSession\Models\CandidateExamStatus;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Row shape for GET /api/v1/exam-sessions (list).
 *
 * Deliberately lighter than ExamSessionResource (used by the single-session
 * endpoints): it works directly off the CandidateExamStatus model and does
 * NOT resolve the current question item, avoiding an N+1 lookup per row.
 *
 * @property-read CandidateExamStatus $resource
 */
class ExamSessionListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $session = $this->resource;

        return [
            'session_id' => (string) $session->session_id,
            'exam_id' => (string) $session->exam_id,
            'candidate_id' => (string) $session->candidate_user_id,
            'enrollment_id' => (string) $session->enrollment_id,
            'state' => (string) $session->session_state,
            'progress' => [
                'total_questions_responded' => (int) $session->total_questions_responded,
                'total_questions_flagged' => (int) $session->total_questions_flagged,
            ],
            'timestamps' => [
                'started_at' => $session->session_started_at?->format(DateTimeInterface::ATOM),
                'resumed_at' => $session->session_resumed_at?->format(DateTimeInterface::ATOM),
                'ended_at' => $session->session_ended_at?->format(DateTimeInterface::ATOM),
                'last_heartbeat_at' => $session->last_heartbeat_at?->format(DateTimeInterface::ATOM),
            ],
            'total_session_duration_seconds' => $session->total_session_duration_seconds !== null
                ? (int) $session->total_session_duration_seconds
                : null,
        ];
    }
}