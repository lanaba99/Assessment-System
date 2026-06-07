<?php

declare(strict_types=1);

namespace App\Domains\Proctoring\Services;

use App\Domains\ExamSession\Exceptions\SessionNotFoundException;
use App\Domains\ExamSession\Repositories\SessionRepository;
use App\Domains\ExamSession\States\ExamSessionStateFactory;
use App\Domains\Proctoring\Contracts\ProctoringService;
use App\Domains\Proctoring\DTOs\LogProctorEventCommand;
use App\Domains\Proctoring\Exceptions\SessionNotProctorableException;
use App\Domains\Proctoring\Models\ProctorLog;
use App\Domains\Proctoring\Repositories\ProctorLogRepository;
use Illuminate\Support\Collection;

class ProctoringServiceImpl implements ProctoringService
{
    public function __construct(
        private readonly ProctorLogRepository $repository,
        private readonly SessionRepository $sessionRepository,
        private readonly ExamSessionStateFactory $stateFactory,
    ) {
    }

    public function logEvent(LogProctorEventCommand $command): ProctorLog
    {
        // Verify the session exists and belongs to this tenant.
        $session = $this->sessionRepository->findById($command->tenantId, $command->sessionId)
            ?? throw SessionNotFoundException::forId($command->sessionId);

        // Reject events on terminal sessions — there is nothing meaningful to
        // measure once a session has completed or been terminated.
        $state = $this->stateFactory->fromSession($session);

        if ($state->isTerminal()) {
            throw SessionNotProctorableException::forTerminalState(
                $command->sessionId,
                $state->name(),
            );
        }

        // Derive candidate and proctor ids from the session and the acting user.
        // When the actor is the candidate, no separate proctor is recorded.
        // When the actor differs (proctor-sourced event), both are captured.
        $candidateId = (string) $session->candidate_user_id;
        $reviewingProctorId = ($command->actorId !== $candidateId) ? $command->actorId : null;

        return $this->repository->create([
            'session_id' => $command->sessionId,
            'candidate_user_id' => $candidateId,
            'tenant_id' => $command->tenantId,
            'reviewing_proctor_id' => $reviewingProctorId,
            'event_timestamp' => $command->eventTimestamp,
            'event_type' => $command->eventType,
            'event_category' => $command->eventCategory,
            'event_payload' => $command->eventPayload,
            'detection_parameters' => $command->detectionParameters,
            'severity_level' => $command->severityLevel,
            'detection_confidence_score' => $command->detectionConfidenceScore,
            'screenshot_url' => $command->screenshotUrl,
            'video_segment_url' => $command->videoSegmentUrl,
            'requires_investigation' => false,
            'is_escalated' => false,
            'investigation_status' => 'open',
            'created_at' => now(),
        ]);
    }

    /**
     * @return Collection<int, ProctorLog>
     */
    public function listForSession(string $tenantId, string $sessionId): Collection
    {
        // Verify the session belongs to this tenant before returning its logs.
        if ($this->sessionRepository->findById($tenantId, $sessionId) === null) {
            throw SessionNotFoundException::forId($sessionId);
        }

        return $this->repository->listForSession($tenantId, $sessionId);
    }
}
