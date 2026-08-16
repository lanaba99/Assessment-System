<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\Services;

use App\Domains\Cohorts\Repositories\CohortMemberRepository;
use App\Domains\ExamEngine\Contracts\QuestionSelectionService;
use App\Domains\ExamEngine\Enums\ExamStatus;
use App\Domains\ExamEngine\Models\Exam;
use App\Domains\ExamEngine\Models\ExamSection;
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
use App\Domains\ExamSession\Exceptions\SessionItemNotFoundException;
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
use App\Domains\Grading\Contracts\GradingService;
use App\Domains\QuestionBank\Contracts\QuestionBankService;
use App\Domains\QuestionBank\DTOs\AdaptiveContext;
use App\Domains\QuestionBank\Models\QuestionVersion;
use App\Domains\QuestionBank\Services\AbilityEstimationService;
use App\Domains\Rules\DTOs\EligibilityContext;
use App\Domains\Rules\Services\EligibilityEvaluatorService;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExamSessionServiceImpl implements ExamSessionService
{
    private const ITEM_STATE_ANSWERED = 'answered';

    // Adaptive exams must define blueprint rows (buildAdaptiveTargets()
    // enforces this) — max item count and target competencies are always
    // derived from them. Only the stopping standard error has a fixed
    // default, since it's a measurement-precision choice, not something
    // the blueprint expresses.
    private const ADAPTIVE_DEFAULT_STOPPING_SE = 0.30;

    public function __construct(
        private readonly SessionRepository $sessionRepository,
        private readonly ExamSessionItemRepository $itemRepository,
        private readonly ExamSessionStateFactory $stateFactory,
        private readonly ExamRepository $examRepository,
        private readonly CohortMemberRepository $cohortMemberRepository,
        private readonly QuestionSelectionService $questionSelection,
        private readonly EligibilityEvaluatorService $eligibilityEvaluator,
        private readonly QuestionBankService $questionBank,
        private readonly AbilityEstimationService $abilityEstimation,
        private readonly GradingService $gradingService,
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

                // --- Gate 6: exam eligibility chain (Rules domain) ---------------
                $eligibilityResult = $this->eligibilityEvaluator->evaluate(
                    new EligibilityContext($tenantId, $candidateId, $examId),
                );

                if (! $eligibilityResult->isEligible) {
                    throw EligibilityViolationException::prerequisiteChainFailed(
                        $eligibilityResult->rejectionReason
                            ?? 'Eligibility requirements for this exam have not been met.',
                    );
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
                // Adaptive exams load questions one at a time during the session:
                // only the FIRST item is created here; subsequent items are
                // selected in submitResponse() after each answer.
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
                } else {
                    $session = $this->startAdaptiveSession($exam, $session);
                }

                return $session;
            }
        );

        $nextItem = $this->itemRepository->findNextPending((string) $session->session_id);

        return $this->toView($session, $nextItem);
    }

    public function listSessions(string $tenantId, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->sessionRepository->paginateList($tenantId, $filters, $perPage);
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
                throw SessionItemNotFoundException::forSessionItemAndSession(
                    $command->sessionItemId,
                    $command->sessionId,
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

                'time_spent_seconds' => $command->timeSpentSeconds ?? 0,
                'time_elapsed_from_start_seconds' => $command->timeElapsedFromStartSeconds ?? 0,

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

            // Adaptive exams pick their next question from the just-graded
            // response, so grading must happen synchronously here rather
            // than waiting for the queued ResponseSubmittedListener. This
            // call is idempotent (AnswerEvaluationRepository::record
            // upserts by session+question), so the listener firing again
            // later from the event() call below is harmless.
            if ($exam !== null && $exam->is_adaptive_exam) {
                $session = $this->advanceAdaptiveSession($exam, $session, $item, $event);
            }

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

    // =========================================================================
    // Adaptive CAT (per-section, sequential)
    // =========================================================================
    //
    // Design: an adaptive exam's sections run their own independent CAT cycle,
    // in section_sequence order. Section 2 does not begin until section 1's
    // stopping rule (max items OR standard error) is satisfied. Each section
    // keeps its own theta/SE/administered-items-count; the session ALSO keeps
    // a single global administered_version_ids list so a question version can
    // never repeat across sections even when two sections target overlapping
    // competencies. All of this is persisted in the existing
    // exam_sessions.session_progress_json column under a "cat" key — no
    // migration required.
    //
    // State shape (session_progress_json['cat']):
    //   version, status ('in_progress'|'completed'), current_section_id,
    //   administered_version_ids (global), sections: {
    //     <section_id>: {
    //       section_id, section_sequence, status ('pending'|'in_progress'|'completed'),
    //       ability_estimate, standard_error, items_administered_count, max_items,
    //       stopping_standard_error, administered_version_ids (this section only),
    //       target_competency_weights, stop_reason,
    //     },
    //   }

    private const ADAPTIVE_CAT_STATE_VERSION = 1;

    private const ADAPTIVE_SECTION_STATUS_PENDING = 'pending';

    private const ADAPTIVE_SECTION_STATUS_IN_PROGRESS = 'in_progress';

    private const ADAPTIVE_SECTION_STATUS_COMPLETED = 'completed';

    /**
     * Selects and creates the first item of the first section for an
     * adaptive session. Called from inside startSession()'s transaction,
     * after the session row itself has been created.
     */
    private function startAdaptiveSession(Exam $exam, CandidateExamStatus $session): CandidateExamStatus
    {
        $sections = $exam->sections->sortBy('section_sequence')->values();

        if ($sections->isEmpty()) {
            throw new RuntimeException(
                "Adaptive exam [{$exam->exam_id}] has no sections — an adaptive exam needs at "
                . 'least one ExamSection to run a CAT cycle against.'
            );
        }

        // Fail fast: validate every section's blueprint config up front
        // rather than discovering a broken section 2 mid-session.
        $sectionsState = [];

        foreach ($sections as $section) {
            $config = $this->buildSectionCatConfig($section);

            $sectionsState[(string) $section->section_id] = [
                'section_id' => (string) $section->section_id,
                'section_sequence' => (int) $section->section_sequence,
                'status' => self::ADAPTIVE_SECTION_STATUS_PENDING,
                'ability_estimate' => 0.0,
                'standard_error' => $this->abilityEstimation->standardError([]),
                'items_administered_count' => 0,
                'max_items' => $config['max_items'],
                'stopping_standard_error' => self::ADAPTIVE_DEFAULT_STOPPING_SE,
                'administered_version_ids' => [],
                'target_competency_weights' => $config['weights'],
                'stop_reason' => null,
            ];
        }

        $firstSection = $sections->first();
        $firstSectionId = (string) $firstSection->section_id;
        $sectionsState[$firstSectionId]['status'] = self::ADAPTIVE_SECTION_STATUS_IN_PROGRESS;

        $firstVersion = $this->resolveNextItemForSection(
            $session,
            $sectionsState[$firstSectionId],
            administeredVersionIdsGlobal: [],
        );

        if ($firstVersion === null) {
            throw new RuntimeException(
                "Adaptive section [{$firstSectionId}] on exam [{$exam->exam_id}] has no eligible "
                . 'calibrated, auto-gradable (mcq/true_false) item to start with.'
            );
        }

        $this->itemRepository->create([
            'session_id' => (string) $session->session_id,
            'section_id' => $firstSectionId,
            'question_version_id' => (string) $firstVersion->version_id,
            'sequence_number' => 1,
            'item_state' => 'pending',
            'version_lock' => 0,
        ]);

        return $this->sessionRepository->updateSession($session, [
            'session_progress_json' => array_merge($session->session_progress_json ?? [], [
                'cat' => [
                    'version' => self::ADAPTIVE_CAT_STATE_VERSION,
                    'status' => 'in_progress',
                    'current_section_id' => $firstSectionId,
                    'administered_version_ids' => [],
                    'sections' => $sectionsState,
                ],
            ]),
        ]);
    }

    /**
     * Grades the just-answered item synchronously, updates that item's
     * section's ability estimate/standard error, and either creates the
     * next item in the same section, advances to the next section, or
     * marks the whole CAT run completed. Called from inside
     * submitResponse()'s transaction, after the response and item have
     * been persisted.
     */
    private function advanceAdaptiveSession(
        Exam $exam,
        CandidateExamStatus $session,
        ExamSessionItem $answeredItem,
        ResponseSubmitted $event,
    ): CandidateExamStatus {
        $cat = (array) ($session->session_progress_json['cat'] ?? null);

        if ($cat === []) {
            throw new RuntimeException(
                "Session [{$session->session_id}] is marked adaptive but has no CAT state — "
                . 'was it started before the adaptive flow was wired up?'
            );
        }

        if ($cat['status'] === 'completed') {
            // CAT already finished (e.g. a retry/duplicate submit); nothing left to select.
            return $session;
        }

        $currentSectionId = (string) $cat['current_section_id'];
        $sectionState = $cat['sections'][$currentSectionId]
            ?? throw new RuntimeException(
                "CAT state on session [{$session->session_id}] has no entry for its own "
                . "current_section_id [{$currentSectionId}]."
            );

        $evaluation = $this->gradingService->gradeFromEvent($event);
        $isCorrect = $evaluation->evaluation_metadata['is_correct'] ?? null;

        if ($isCorrect === null) {
            throw new RuntimeException(
                "Question version [{$answeredItem->question_version_id}] on adaptive exam "
                . "[{$exam->exam_id}] is not auto-gradable — adaptive item pools must be "
                . 'restricted to auto-gradable question types (mcq, true_false).'
            );
        }

        $version = QuestionVersion::query()
            ->with('psychometrics')
            ->whereKey((string) $answeredItem->question_version_id)
            ->firstOrFail();

        $difficultyLogit = $this->abilityEstimation->logitDifficulty(
            (float) ($version->psychometrics?->difficulty_index ?? 0.5),
        );

        $itemsBefore = (int) $sectionState['items_administered_count'];

        $newTheta = $this->abilityEstimation->update(
            (float) $sectionState['ability_estimate'],
            $difficultyLogit,
            (bool) $isCorrect,
            $itemsBefore,
        );

        $sectionState['administered_version_ids'][] = (string) $answeredItem->question_version_id;
        $sectionState['items_administered_count'] = $itemsBefore + 1;
        $sectionState['ability_estimate'] = $newTheta;

        // Standard error is derived from this section's own accumulated
        // item information only — a candidate's precision on section 2
        // does not inherit section 1's information. We accumulate the
        // running total incrementally rather than storing every item's
        // information and re-summing each time.
        $newItemInformation = $this->abilityEstimation->itemInformation($difficultyLogit, $newTheta);
        $sectionState['cumulative_item_information'] = (float) ($sectionState['cumulative_item_information'] ?? 0.0)
            + $newItemInformation;
        $sectionState['standard_error'] = $this->abilityEstimation->standardErrorFromTotal(
            $sectionState['cumulative_item_information'],
        );

        $cat['administered_version_ids'][] = (string) $answeredItem->question_version_id;

        $sectionStopped = $sectionState['items_administered_count'] >= $sectionState['max_items']
            || $sectionState['standard_error'] <= $sectionState['stopping_standard_error'];

        $nextVersion = null;

        if (! $sectionStopped) {
            $nextVersion = $this->resolveNextItemForSection(
                $session,
                $sectionState,
                administeredVersionIdsGlobal: $cat['administered_version_ids'],
            );

            if ($nextVersion === null) {
                // Pool exhausted before the section's own stop rule fired —
                // this section is done, just for a different reason.
                $sectionStopped = true;
                $sectionState['stop_reason'] = 'pool_exhausted';
            }
        } else {
            $sectionState['stop_reason'] = $sectionState['items_administered_count'] >= $sectionState['max_items']
                ? 'max_items_reached'
                : 'stopping_standard_error_reached';
        }

        if ($nextVersion !== null) {
            $this->itemRepository->create([
                'session_id' => (string) $session->session_id,
                'section_id' => $currentSectionId,
                'question_version_id' => (string) $nextVersion->version_id,
                // sequence_number is unique per SESSION (not per section —
                // see exam_session_items' unique(['session_id',
                // 'sequence_number']) constraint), so it must keep counting
                // up across a section boundary rather than restart at 1.
                'sequence_number' => count($cat['administered_version_ids']) + 1,
                'item_state' => 'pending',
                'version_lock' => 0,
            ]);

            $cat['sections'][$currentSectionId] = $sectionState;

            return $this->sessionRepository->updateSession($session, [
                'session_progress_json' => array_merge($session->session_progress_json ?? [], ['cat' => $cat]),
            ]);
        }

        // This section is done — mark it completed and move to the next
        // pending section in sequence order, if any.
        $sectionState['status'] = self::ADAPTIVE_SECTION_STATUS_COMPLETED;
        $cat['sections'][$currentSectionId] = $sectionState;

        $nextSectionEntry = collect($cat['sections'])
            ->filter(fn (array $s): bool => $s['status'] === self::ADAPTIVE_SECTION_STATUS_PENDING)
            ->sortBy('section_sequence')
            ->first();

        if ($nextSectionEntry === null) {
            // No more sections — the whole adaptive run is complete.
            $cat['status'] = 'completed';
            $cat['current_section_id'] = null;

            return $this->sessionRepository->updateSession($session, [
                'session_progress_json' => array_merge($session->session_progress_json ?? [], ['cat' => $cat]),
            ]);
        }

        $nextSectionId = $nextSectionEntry['section_id'];
        $nextSectionEntry['status'] = self::ADAPTIVE_SECTION_STATUS_IN_PROGRESS;
        $cat['sections'][$nextSectionId] = $nextSectionEntry;
        $cat['current_section_id'] = $nextSectionId;

        $firstVersionOfNextSection = $this->resolveNextItemForSection(
            $session,
            $nextSectionEntry,
            administeredVersionIdsGlobal: $cat['administered_version_ids'],
        );

        if ($firstVersionOfNextSection === null) {
            throw new RuntimeException(
                "Adaptive section [{$nextSectionId}] on exam [{$exam->exam_id}] has no eligible "
                . 'item left once items already administered in earlier sections are excluded — '
                . 'its competency pool overlaps too heavily with a prior section.'
            );
        }

        $this->itemRepository->create([
            'session_id' => (string) $session->session_id,
            'section_id' => $nextSectionId,
            'question_version_id' => (string) $firstVersionOfNextSection->version_id,
            'sequence_number' => count($cat['administered_version_ids']) + 1,
            'item_state' => 'pending',
            'version_lock' => 0,
        ]);

        return $this->sessionRepository->updateSession($session, [
            'session_progress_json' => array_merge($session->session_progress_json ?? [], ['cat' => $cat]),
        ]);
    }

    /**
     * Resolves the next item for a section using that section's OWN
     * current theta/standard-error/weights, while excluding every version
     * already administered ANYWHERE in the session (not just this
     * section) — this is what guarantees no repeats across sections.
     *
     * The AdaptiveContext passed to QuestionBankService is deliberately
     * built with maxItems=PHP_INT_MAX and stoppingStandardError=-1.0: the
     * stop/continue decision is made by OUR OWN per-section state (see
     * advanceAdaptiveSession), not by resolveNextAdaptiveItem's internal
     * count($administeredVersionIds) >= maxItems check — that check would
     * be wrong here because $administeredVersionIds is the GLOBAL list
     * (needed for correct exclusion) while maxItems is a PER-SECTION
     * limit; comparing the two would make section 2 stop before it starts.
     *
     * @param  array<string, mixed>  $sectionState
     * @param  array<int, string>  $administeredVersionIdsGlobal
     */
    private function resolveNextItemForSection(
        CandidateExamStatus $session,
        array $sectionState,
        array $administeredVersionIdsGlobal,
    ): ?QuestionVersion {
        $context = new AdaptiveContext(
            sessionId: (string) $session->session_id,
            candidateId: (string) $session->candidate_user_id,
            tenantId: (string) $session->tenant_id,
            abilityEstimate: (float) $sectionState['ability_estimate'],
            standardError: (float) $sectionState['standard_error'],
            administeredVersionIds: $administeredVersionIdsGlobal,
            targetCompetencyWeights: $sectionState['target_competency_weights'],
            maxItems: PHP_INT_MAX,
            stoppingStandardError: -1.0,
        );

        return $this->questionBank->resolveNextAdaptiveItem($context);
    }

    /**
     * @return array{weights: array<string, float>, max_items: int}
     */
    private function buildSectionCatConfig(ExamSection $section): array
    {
        $blueprints = $section->blueprints;

        if ($blueprints->isEmpty()) {
            // findEligibleVersionsForCompetencies() filters items by
            // whereIn('competency_id', $competencyIds) — an empty list
            // matches nothing, so every CAT section MUST define at least
            // one blueprint row naming which competencies it draws from.
            throw new RuntimeException(
                "Adaptive section [{$section->section_id}] has no blueprint rows — every CAT "
                . 'section must define at least one ExamBlueprint (competency + weight).'
            );
        }

        $rawWeights = $blueprints
            ->groupBy(fn ($bp) => (string) $bp->competency_id)
            ->map(fn ($group) => (float) $group->sum('min_weight_percentage'));

        $weightSum = (float) $rawWeights->sum();

        $weights = $rawWeights
            ->map(fn (float $w): float => $weightSum > 0 ? round(($w / $weightSum) * 100, 4) : 0.0)
            ->toArray();

        $maxItems = (int) $section->questions_in_section > 0
            ? (int) $section->questions_in_section
            : max(1, (int) $blueprints->sum('max_questions_count'));

        return ['weights' => $weights, 'max_items' => $maxItems];
    }

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