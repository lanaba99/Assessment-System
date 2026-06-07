<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\Contracts;

use App\Domains\ExamSession\DTOs\ExamSessionView;
use App\Domains\ExamSession\DTOs\SubmitResponseCommand;
use App\Domains\ExamSession\Exceptions\EligibilityViolationException;
use App\Domains\ExamSession\Exceptions\EnrollmentNotFoundException;
use App\Domains\ExamSession\Exceptions\InvalidSessionStateException;
use App\Domains\ExamSession\Exceptions\SessionDurationExceededException;
use App\Domains\ExamSession\Exceptions\SessionNotFoundException;
use App\Domains\ExamSession\Exceptions\StaleVersionLockException;
use App\Domains\ExamSession\Models\CandidateExamStatus;

interface ExamSessionService
{
    /**
     * Open or resume a session for the given candidate on the given exam.
     *
     * The full eligibility gate is enforced in this order:
     *   1. Exam must exist within the tenant and be in Published status.
     *   2. No prior non-terminal session exists (idempotent — returns it if one does).
     *   3. An active enrollment exists for (tenant, candidate, exam).
     *   4. The enrollment passes all eligibility conditions (status, window, attempts).
     *   5. If the enrollment is cohort-scoped, the candidate must be an active member.
     *
     * @throws EligibilityViolationException when any eligibility condition fails
     * @throws EnrollmentNotFoundException   when no enrollment exists at all
     */
    public function startSession(string $tenantId, string $candidateId, string $examId): ExamSessionView;

    /**
     * Load the raw session model for the controller's authorization layer.
     * For returning a view to the client, call getSession() instead.
     *
     * @throws SessionNotFoundException when the session does not exist for this tenant
     */
    public function loadSessionModel(string $tenantId, string $sessionId): CandidateExamStatus;

    /**
     * @throws SessionNotFoundException when the session does not exist for this tenant
     */
    public function getSession(string $tenantId, string $sessionId): ExamSessionView;

    /**
     * @throws SessionNotFoundException         when the session does not exist
     * @throws InvalidSessionStateException     when the session is not in_progress
     * @throws StaleVersionLockException        when a concurrent modification is detected
     * @throws SessionDurationExceededException when the exam's total_duration_minutes has elapsed
     */
    public function submitResponse(SubmitResponseCommand $command): ExamSessionView;

    /**
     * Pause an in_progress session. Transitions: in_progress → paused.
     *
     * @throws SessionNotFoundException     when the session does not exist for this tenant
     * @throws InvalidSessionStateException when the session cannot be suspended
     */
    public function suspendSession(string $tenantId, string $sessionId): ExamSessionView;

    /**
     * Resume a paused session. Transitions: paused → in_progress.
     *
     * @throws SessionNotFoundException     when the session does not exist for this tenant
     * @throws InvalidSessionStateException when the session cannot be resumed
     */
    public function resumeSession(string $tenantId, string $sessionId): ExamSessionView;

    /**
     * Mark a session as candidate-completed. Transitions: in_progress → completed.
     *
     * Fires ExamSessionCompleted UNLESS the acting user is a manager (not the
     * candidate) AND the session has zero recorded responses. This prevents
     * ghost zero-score grade records for sessions that were never used.
     *
     * @param  string  $actorId  Authenticated user's id — used to distinguish
     *                            candidate-initiated from manager-initiated endings.
     *
     * @throws SessionNotFoundException     when the session does not exist for this tenant
     * @throws InvalidSessionStateException when the session cannot be completed
     */
    public function completeSession(string $tenantId, string $sessionId, string $actorId): ExamSessionView;

    /**
     * Forcibly end a session (admin/proctor action, or system timeout).
     * Transitions: any non-terminal state → terminated.
     *
     * Fires ExamSessionCompleted UNLESS the acting user is a manager (not the
     * candidate) AND the session has zero recorded responses.
     *
     * @param  string  $actorId  Authenticated user's id — used to distinguish
     *                            candidate-initiated from manager-initiated endings.
     *
     * @throws SessionNotFoundException     when the session does not exist for this tenant
     * @throws InvalidSessionStateException when the session is already in a terminal state
     */
    public function terminateSession(string $tenantId, string $sessionId, string $actorId): ExamSessionView;

    /**
     * Record a keep-alive heartbeat from the candidate's browser.
     *
     * Updates last_heartbeat_at without incrementing version_lock — heartbeats
     * are a side-channel signal and must not interfere with concurrent response
     * submission which relies on the version_lock for optimistic concurrency.
     *
     * @throws SessionNotFoundException     when the session does not exist for this tenant
     * @throws InvalidSessionStateException when the session cannot record a heartbeat
     *                                      (i.e. the session is in a terminal state)
     */
    public function recordHeartbeat(
        string $tenantId,
        string $sessionId,
        ?array $metadata = null,
    ): ExamSessionView;
}
