<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\Services;

use App\Domains\Cohorts\Repositories\CohortMemberRepository;
use App\Domains\ExamEngine\Contracts\QuestionSelectionService;
use App\Domains\ExamEngine\Enums\ExamStatus;
use App\Domains\ExamEngine\Repositories\ExamRepository;
use App\Domains\ExamSession\Contracts\ExamSessionService;
use App\Domains\ExamSession\DTOs\ExamSessionView;
use App\Domains\ExamSession\DTOs\SubmitResponseCommand;
use App\Domains\ExamSession\Events\ExamSessionCompleted;
use App\Domains\ExamSession\Events\ResponseSubmitted;
use App\Domains\ExamSession\Exceptions\EligibilityViolationException;
use App\Domains\ExamSession\Exceptions\EnrollmentNotFoundException;
use App\Domains\ExamSession\Exceptions\InvalidSessionStateException;
use App\Domains\ExamSession\Exceptions\SessionDurationExceededException;
use App\Domains\ExamSession\Exceptions\SessionNotFoundException;
use App\Domains\ExamSession\Models\CandidateExamStatus;
use App\Domains\ExamSession\Models\ExamSessionItem;
use App\Domains\ExamSession\Repositories\ExamSessionItemRepository;
use App\Domains\ExamSession\Repositories\SessionRepository;
use App\Domains\ExamSession\States\ActiveState;
use App\Domains\ExamSession\States\CompletedState;
use App\Domains\ExamSession\States\ExamSessionStateFactory;
use App\Domains\ExamSession\States\PendingState;
use App\Domains\ExamSession\States\SuspendedState;
use App\Domains\ExamSession\States\TerminatedState;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExamSessionServiceImpl implements ExamSessionService
{
    private const ITEM_STATE_ANSWERED = 'answered';

    public function __construct(
        private readonly SessionRepository $sessionRepository,
        private readonly ExamSessionItemRepository $itemRepository,
        private readonly ExamSessionStateFactory $stateFactory,
        private readonly ExamRepository $examRepository,
        private readonly CohortMemberRepository $cohortMemberRepository,
        private readonly QuestionSelectionService $questionSelection,
    ) {
    }

    // =========================================================================
    // Session lifecycle
    // =========================================================================

    public function startSession(string $tenantId, string $candidateId, string $examId): ExamSessionView
    {
        // Gate 1 runs outside the transaction: it is a read-only guard against a
        // well-indexed exam row and carries no mutation risk. Throwing early avoids
        // opening a transaction when the exam is simply not published.
        $exam = $this->examRepository->findWithSectionsAndBlueprints($tenantId, $examId);

        if ($exam === null || $exam->exam_status !== ExamStatus::Published) {
            throw EligibilityViolationException::examNotPublished($examId);
        }

        // Gates 2–5 and the session creation are wrapped in a single transaction.
        //
        // The FIRST operation inside the transaction is a pessimistic row-level
        // lock on the enrollment record (findEnrollmentForUpdate). Any concurrent
        // startSession call for the same (tenantId, candidateId, examId) tuple
        // will block at that point until this transaction either commits or rolls
        // back. This turns the previously non-atomic check-then-act sequence into
        // a serialized, atomic operation:
        //
        //   Request A: acquires lock → idempotency check (null) → all gates → create session → commit
        //   Request B: blocks on lock → acquires lock → idempotency check (finds A's session) → returns it
        //
        // On SQLite (used in the test suite) lockForUpdate() is a grammar no-op;
        // the serialization guarantee is a production-only concern for MySQL/Postgres.
        $session = DB::transaction(
            function () use ($exam, $tenantId, $candidateId, $examId): CandidateExamStatus {

                // --- Gate 3 + lock: enrollment must exist, and we hold the row lock ---
                $enrollment = $this->sessionRepository->findEnrollmentForUpdate(
                    $tenantId,
                    $candidateId,
                    $examId,
                );

                if ($enrollment === null) {
                    throw EnrollmentNotFoundException::forCandidate($candidateId, $examId);
                }

                // --- Gate 2 (inside lock scope): idempotent re-start ---
                // Re-check after acquiring the lock so that a session committed
                // by a concurrent request between Gate 1 and the lock acquisition
                // is visible here and returned without creating a duplicate.
                $existing = $this->sessionRepository->findActiveSession(
                    $tenantId,
                    $candidateId,
                    $examId,
                    $this->nonTerminalStateNames(),
                );

                if ($existing !== null) {
                    return $existing;
                }

                // --- Gate 4: enrollment eligibility conditions ----------------

                if ($enrollment->enrollment_status !== 'active') {
                    throw EligibilityViolationException::inactiveEnrollment(
                        (string) $enrollment->enrollment_id
                    );
                }

                if ($enrollment->start_window_date !== null && $enrollment->start_window_date->isFuture()) {
                    throw EligibilityViolationException::windowNotOpen(
                        (string) $enrollment->enrollment_id
                    );
                }

                if ($enrollment->end_window_date !== null && $enrollment->end_window_date->isPast()) {
                    throw EligibilityViolationException::windowNotOpen(
                        (string) $enrollment->enrollment_id
                    );
                }

                if ((int) $enrollment->attempts_used >= (int) $enrollment->max_attempts_allowed) {
                    throw EligibilityViolationException::attemptsExhausted(
                        (string) $enrollment->enrollment_id
                    );
                }

                // --- Gate 5: cohort membership (only when enrollment is cohort-scoped) ---
                if ($enrollment->cohort_id !== null) {
                    $isMember = $this->cohortMemberRepository->isActiveMember(
                        (string) $enrollment->cohort_id,
                        $candidateId,
                    );

                    if (! $isMember) {
                        throw EligibilityViolationException::notCohortMember(
                            $candidateId,
                            (string) $enrollment->cohort_id,
                        );
                    }
                }

                // --- All gates passed: create session and pre-populate items --
                $activeState = (new PendingState())->transitionOnStart();

                $session = $this->sessionRepository->createSession([
                    'exam_id' => $examId,
                    'enrollment_id' => (string) $enrollment->enrollment_id,
                    'candidate_user_id' => $candidateId,
                    'tenant_id' => $tenantId,
                    'session_state' => $activeState->name(),
                    'session_started_at' => now(),
                    'version_lock' => 0,
                ]);

                // Fixed-question and randomised exams get all items pre-created.
                // Adaptive exams load questions one at a time during the session.
                if (! $exam->is_adaptive_exam) {
                    $items = $this->questionSelection->resolveQuestionsForSession(
                        $exam,
                        $candidateId,
                    );

                    foreach ($items->values() as $sequence => $item) {
                        $this->itemRepository->create([
                            'session_id' => (string) $session->session_id,
                            'section_id' => $item->sectionId,
                            'question_version_id' => $item->questionVersionId,
                            'sequence_number' => $sequence + 1,
                            'item_state' => 'pending',
                            'version_lock' => 0,
                        ]);
                    }
                }

                return $session;
            }
        );

        $nextItem = $this->itemRepository->findNextPending((string) $session->session_id);

        return $this->toView($session, $nextItem);
    }

    public function loadSessionModel(string $tenantId, string $sessionId): CandidateExamStatus
    {
        return $this->sessionRepository->findById($tenantId, $sessionId)
            ?? throw SessionNotFoundException::forId($sessionId);
    }

    public function getSession(string $tenantId, string $sessionId): ExamSessionView
    {
        $session = $this->sessionRepository->findById($tenantId, $sessionId)
            ?? throw SessionNotFoundException::forId($sessionId);

        $nextItem = $this->itemRepository->findNextPending($sessionId);

        return $this->toView($session, $nextItem);
    }

    public function submitResponse(SubmitResponseCommand $command): ExamSessionView
    {
        /** @var array{view: ExamSessionView, event: ResponseSubmitted} $result */
        $result = DB::transaction(function () use ($command): array {
            $session = $this->sessionRepository->findByIdForUpdate(
                $command->tenantId,
                $command->sessionId,
            ) ?? throw SessionNotFoundException::forId($command->sessionId);

            $this->assertCandidateOwnership($session, $command->candidateId);

            $state = $this->stateFactory->fromSession($session);

            if (! $state->canSubmitResponse()) {
                throw InvalidSessionStateException::forOperation('submit_response', $state->name());
            }

            // Duration guard: reject if the exam's time limit has been exceeded.
            // This check runs after the state check so that invalid-state sessions
            // (e.g. paused) still return the correct state error, not a duration error.
            $exam = $this->examRepository->findById($command->tenantId, (string) $session->exam_id);

            if ($exam !== null && $session->session_started_at !== null) {
                $elapsedMinutes = (int) $session->session_started_at->diffInMinutes(now());

                if ($elapsedMinutes > (int) $exam->total_duration_minutes) {
                    throw SessionDurationExceededException::forSession(
                        $command->sessionId,
                        (int) $exam->total_duration_minutes,
                    );
                }
            }

            $item = $this->itemRepository->findByIdForUpdate($command->sessionItemId);

            if ($item === null || (string) $item->session_id !== $command->sessionId) {
                throw new RuntimeException(
                    "Session item [{$command->sessionItemId}] not found on session [{$command->sessionId}]."
                );
            }

            if ($command->expectedItemVersionLock !== null
                && (int) $item->version_lock !== $command->expectedItemVersionLock) {
                throw \App\Domains\ExamSession\Exceptions\StaleVersionLockException::forSessionItem(
                    (string) $item->session_item_id,
                    $command->expectedItemVersionLock,
                );
            }

            $submittedAt = new DateTimeImmutable();

            $this->sessionRepository->recordResponse([
                'session_id' => (string) $session->session_id,
                'question_version_id' => (string) $item->question_version_id,
                'candidate_user_id' => (string) $session->candidate_user_id,
                'tenant_id' => (string) $session->tenant_id,
                'question_sequence_number' => (int) $item->sequence_number,
                'response_type' => $command->responseType,
                'response_data' => $command->responseData,
                'response_text' => $command->responseText,
                'selected_options_json' => $command->selectedOptions,
                'file_upload_url' => $command->fileUploadUrl,
                'time_spent_seconds' => $command->timeSpentSeconds,
                'time_elapsed_from_start_seconds' => $command->timeElapsedFromStartSeconds,
                'is_flagged_for_review' => $command->isFlaggedForReview,
                'response_submitted_at' => now(),
            ]);

            $item = $this->itemRepository->update($item, [
                'item_state' => self::ITEM_STATE_ANSWERED,
                'answered_at' => now(),
                'is_flagged' => $command->isFlaggedForReview,
            ]);

            $session = $this->sessionRepository->updateSession($session, [
                'total_questions_responded' => (int) $session->total_questions_responded + 1,
                'total_questions_flagged' => (int) $session->total_questions_flagged
                    + ($command->isFlaggedForReview ? 1 : 0),
            ]);

            $event = new ResponseSubmitted(
                command: $command,
                questionVersionId: (string) $item->question_version_id,
                sectionId: (string) $item->section_id,
                questionSequenceNumber: (int) $item->sequence_number,
                sessionItemVersionLockAfter: (int) $item->version_lock,
                sessionVersionLockAfter: (int) $session->version_lock,
                totalQuestionsResponded: (int) $session->total_questions_responded,
                totalQuestionsFlagged: (int) $session->total_questions_flagged,
                submittedAt: $submittedAt,
            );

            // Load the next pending item now, while still inside the transaction,
            // so the view is accurate immediately after the answer is committed.
            $nextItem = $this->itemRepository->findNextPending((string) $session->session_id);

            return [
                'view' => $this->toView($session, $nextItem),
                'event' => $event,
            ];
        });

        event($result['event']);

        return $result['view'];
    }

    public function suspendSession(string $tenantId, string $sessionId): ExamSessionView
    {
        $session = DB::transaction(function () use ($tenantId, $sessionId): CandidateExamStatus {
            $session = $this->sessionRepository->findByIdForUpdate($tenantId, $sessionId)
                ?? throw SessionNotFoundException::forId($sessionId);

            $state = $this->stateFactory->fromSession($session);

            if (! $state->canSuspend()) {
                throw InvalidSessionStateException::forOperation('suspend', $state->name());
            }

            $next = $state->transitionOnSuspend();

            return $this->sessionRepository->updateSession($session, [
                'session_state' => $next->name(),
            ]);
        });

        $nextItem = $this->itemRepository->findNextPending($sessionId);

        return $this->toView($session, $nextItem);
    }

    public function resumeSession(string $tenantId, string $sessionId): ExamSessionView
    {
        $session = DB::transaction(function () use ($tenantId, $sessionId): CandidateExamStatus {
            $session = $this->sessionRepository->findByIdForUpdate($tenantId, $sessionId)
                ?? throw SessionNotFoundException::forId($sessionId);

            $state = $this->stateFactory->fromSession($session);

            if (! $state->canResume()) {
                throw InvalidSessionStateException::forOperation('resume', $state->name());
            }

            $next = $state->transitionOnResume();

            return $this->sessionRepository->updateSession($session, [
                'session_state' => $next->name(),
                'session_resumed_at' => now(),
            ]);
        });

        $nextItem = $this->itemRepository->findNextPending($sessionId);

        return $this->toView($session, $nextItem);
    }

    public function completeSession(string $tenantId, string $sessionId, string $actorId): ExamSessionView
    {
        /** @var array{session: CandidateExamStatus, shouldFireEvent: bool} $result */
        $result = DB::transaction(function () use ($tenantId, $sessionId, $actorId): array {
            $session = $this->sessionRepository->findByIdForUpdate($tenantId, $sessionId)
                ?? throw SessionNotFoundException::forId($sessionId);

            $state = $this->stateFactory->fromSession($session);

            if (! $state->canComplete()) {
                throw InvalidSessionStateException::forOperation('complete', $state->name());
            }

            $next = $state->transitionOnComplete();
            $endedAt = new DateTimeImmutable();

            $session = $this->sessionRepository->updateSession($session, [
                'session_state' => $next->name(),
                'session_ended_at' => now(),
                'completion_method' => 'candidate_submitted',
            ]);

            // Zero-response guard: suppress the grading pipeline when a manager
            // ends an empty session. A candidate completing their own empty session
            // (e.g. explicit early finish) still gets a result recorded so their
            // attempt is consumed.
            $candidateInitiated = (string) $session->candidate_user_id === $actorId;
            $shouldFireEvent = $candidateInitiated || (int) $session->total_questions_responded > 0;

            return [
                'session' => $session,
                'event' => $this->buildCompletionEvent($session, $endedAt),
                'shouldFireEvent' => $shouldFireEvent,
            ];
        });

        if ($result['shouldFireEvent']) {
            event($result['event']);
        }

        return $this->toView($result['session']);
    }

    public function terminateSession(string $tenantId, string $sessionId, string $actorId): ExamSessionView
    {
        /** @var array{session: CandidateExamStatus, event: ExamSessionCompleted, shouldFireEvent: bool} $result */
        $result = DB::transaction(function () use ($tenantId, $sessionId, $actorId): array {
            $session = $this->sessionRepository->findByIdForUpdate($tenantId, $sessionId)
                ?? throw SessionNotFoundException::forId($sessionId);

            $state = $this->stateFactory->fromSession($session);

            if (! $state->canTerminate()) {
                throw InvalidSessionStateException::forOperation('terminate', $state->name());
            }

            $next = $state->transitionOnTerminate();
            $endedAt = new DateTimeImmutable();

            $session = $this->sessionRepository->updateSession($session, [
                'session_state' => $next->name(),
                'session_ended_at' => now(),
                'completion_method' => 'terminated',
            ]);

            // Zero-response guard: a manager terminating a never-used session
            // must not produce a ghost zero-score grade record. A candidate self-
            // terminating their own empty session still fires so the attempt is
            // consumed (preventing retake abuse).
            $candidateInitiated = (string) $session->candidate_user_id === $actorId;
            $shouldFireEvent = $candidateInitiated || (int) $session->total_questions_responded > 0;

            return [
                'session' => $session,
                'event' => $this->buildCompletionEvent($session, $endedAt),
                'shouldFireEvent' => $shouldFireEvent,
            ];
        });

        if ($result['shouldFireEvent']) {
            event($result['event']);
        }

        return $this->toView($result['session']);
    }

    public function recordHeartbeat(
        string $tenantId,
        string $sessionId,
        ?array $metadata = null,
    ): ExamSessionView {
        $session = $this->sessionRepository->findById($tenantId, $sessionId)
            ?? throw SessionNotFoundException::forId($sessionId);

        $state = $this->stateFactory->fromSession($session);

        if (! $state->canRecordHeartbeat()) {
            throw InvalidSessionStateException::forOperation('heartbeat', $state->name());
        }

        // Update only last_heartbeat_at — version_lock is intentionally left
        // untouched. See SessionRepository::updateHeartbeat for the rationale.
        $this->sessionRepository->updateHeartbeat($tenantId, $sessionId, $metadata);

        // Reflect the heartbeat timestamp in the view without a second round-trip.
        // The exact value may differ from the DB by microseconds — acceptable here.
        $session->forceFill(['last_heartbeat_at' => now()]);

        $nextItem = $this->itemRepository->findNextPending($sessionId);

        return $this->toView($session, $nextItem);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Builds the ExamSessionView DTO from a session model. When the current
     * item is passed in it is used directly; otherwise the next pending item
     * is NOT loaded here (callers must supply it to avoid redundant queries).
     */
    private function toView(
        CandidateExamStatus $session,
        ?ExamSessionItem $currentItem = null,
    ): ExamSessionView {
        return new ExamSessionView(
            sessionId: (string) $session->session_id,
            tenantId: (string) $session->tenant_id,
            examId: (string) $session->exam_id,
            candidateId: (string) $session->candidate_user_id,
            enrollmentId: (string) $session->enrollment_id,
            state: (string) $session->session_state,
            currentSessionItemId: $currentItem !== null
                ? (string) $currentItem->session_item_id
                : null,
            currentQuestionVersionId: $currentItem !== null
                ? (string) $currentItem->question_version_id
                : null,
            currentSectionId: $currentItem !== null
                ? (string) $currentItem->section_id
                : null,
            currentQuestionIndex: $currentItem !== null
                ? (int) $currentItem->sequence_number
                : (int) $session->current_question_index,
            totalQuestionsResponded: (int) $session->total_questions_responded,
            totalQuestionsFlagged: (int) $session->total_questions_flagged,
            sessionStartedAt: $this->toDateTime($session->session_started_at),
            sessionResumedAt: $this->toDateTime($session->session_resumed_at),
            sessionEndedAt: $this->toDateTime($session->session_ended_at),
            totalSessionDurationSeconds: $session->total_session_duration_seconds !== null
                ? (int) $session->total_session_duration_seconds
                : null,
            lastHeartbeatAt: $this->toDateTime($session->last_heartbeat_at),
            versionLock: (int) $session->version_lock,
            progressJson: $session->session_progress_json ?? [],
        );
    }

    private function buildCompletionEvent(
        CandidateExamStatus $session,
        DateTimeImmutable $endedAt,
    ): ExamSessionCompleted {
        return new ExamSessionCompleted(
            sessionId: (string) $session->session_id,
            tenantId: (string) $session->tenant_id,
            candidateId: (string) $session->candidate_user_id,
            examId: (string) $session->exam_id,
            finalState: (string) $session->session_state,
            completionMethod: (string) $session->completion_method,
            endedAt: $endedAt,
            totalQuestionsResponded: (int) $session->total_questions_responded,
            totalQuestionsFlagged: (int) $session->total_questions_flagged,
            versionLockAfter: (int) $session->version_lock,
        );
    }

    private function assertCandidateOwnership(
        CandidateExamStatus $session,
        string $candidateId,
    ): void {
        if ((string) $session->candidate_user_id !== $candidateId) {
            throw new RuntimeException(
                "Candidate [{$candidateId}] cannot submit a response on session [{$session->session_id}]."
            );
        }
    }

    /**
     * @return array<int, string>
     */
    private function nonTerminalStateNames(): array
    {
        return array_filter(
            $this->allStateNames(),
            fn (string $name): bool => ! $this->stateFactory->fromName($name)->isTerminal(),
        );
    }

    /**
     * @return array<int, string>
     */
    private function allStateNames(): array
    {
        return [
            PendingState::NAME,
            ActiveState::NAME,
            SuspendedState::NAME,
            CompletedState::NAME,
            TerminatedState::NAME,
        ];
    }

    private function toDateTime(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        return new DateTimeImmutable((string) $value);
    }
}
