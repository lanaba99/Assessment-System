<?php

declare(strict_types=1);

namespace App\Domains\Proctoring\Contracts;

use App\Domains\ExamSession\Exceptions\SessionNotFoundException;
use App\Domains\Proctoring\DTOs\LogProctorEventCommand;
use App\Domains\Proctoring\Exceptions\SessionNotProctorableException;
use App\Domains\Proctoring\Models\ProctorLog;
use Illuminate\Support\Collection;

interface ProctoringService
{
    /**
     * Record a single proctoring event for an active or paused session.
     *
     * The service derives candidate_user_id from the session model to ensure
     * accuracy. If the acting user differs from the session's candidate, the
     * actor is recorded as the reviewing_proctor_id.
     *
     * @throws SessionNotFoundException         when the session does not exist for this tenant
     * @throws SessionNotProctorableException   when the session is in a terminal state
     */
    public function logEvent(LogProctorEventCommand $command): ProctorLog;

    /**
     * Return all proctoring events for a session, ordered by event_timestamp descending.
     *
     * @return Collection<int, ProctorLog>
     *
     * @throws SessionNotFoundException when the session does not exist for this tenant
     */
    public function listForSession(string $tenantId, string $sessionId): Collection;
}
