<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\ExamSession\DTOs\ExamSessionView;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read ExamSessionView $resource
 */
class ExamSessionResource extends JsonResource
{
    public static $wrap = 'data';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $view = $this->resource;

        return [
            'session_id' => $view->sessionId,
            'tenant_id' => $view->tenantId,
            'exam_id' => $view->examId,
            'candidate_id' => $view->candidateId,
            'enrollment_id' => $view->enrollmentId,
            'state' => $view->state,
            'current' => [
                'session_item_id' => $view->currentSessionItemId,
                'question_version_id' => $view->currentQuestionVersionId,
                'section_id' => $view->currentSectionId,
                'question_index' => $view->currentQuestionIndex,
            ],
            'progress' => [
                'total_questions_responded' => $view->totalQuestionsResponded,
                'total_questions_flagged' => $view->totalQuestionsFlagged,
                'progress_data' => $view->progressJson,
            ],
            'timestamps' => [
                'started_at' => $view->sessionStartedAt?->format(DateTimeInterface::ATOM),
                'resumed_at' => $view->sessionResumedAt?->format(DateTimeInterface::ATOM),
                'ended_at' => $view->sessionEndedAt?->format(DateTimeInterface::ATOM),
                'last_heartbeat_at' => $view->lastHeartbeatAt?->format(DateTimeInterface::ATOM),
            ],
            'total_session_duration_seconds' => $view->totalSessionDurationSeconds,
            'version_lock' => $view->versionLock,
        ];
    }
}
