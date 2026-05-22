<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\Services;

use App\Domains\ExamEngine\Repositories\ExamRepository;
use App\Domains\ExamSession\DTOs\ExamSessionView;
use App\Domains\ExamSession\DTOs\SubmitResponseCommand;
use App\Domains\ExamSession\Events\ExamSessionCompleted;
use App\Domains\ExamSession\Events\ResponseSubmitted;
use App\Domains\ExamSession\Exceptions\InvalidSessionStateException;
use App\Domains\ExamSession\Exceptions\StaleVersionLockException;
use App\Domains\ExamSession\Models\CandidateExamStatus;
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

class ExamSessionService
{
    private const ITEM_STATE_ANSWERED = 'answered';

    public function __construct(
        private readonly ExamRepository $examRepository,
        private readonly SessionRepository $sessionRepository,
        private readonly ExamSessionItemRepository $itemRepository,
        private readonly ExamSessionStateFactory $stateFactory,
    ) {
    }

    public function startSession(string $candidateId, string $examId): ExamSessionView
    {
        $exam = $this->examRepository->findWithSectionsAndQuestions($examId);

        if ($exam === null) {
            throw new RuntimeException("Exam {$examId} not found.");
        }

        $active = $this->sessionRepository->findActiveSession(
            $candidateId,
            $examId,
            $this->nonTerminalStateNames(),
        );

        if ($active !== null) {
            return $this->toView($active);
        }

        $enrollment = $this->sessionRepository->findEnrollment($candidateId, $examId);

        if ($enrollment === null) {
            throw new RuntimeException("Candidate {$candidateId} is not enrolled in exam {$examId}.");
        }

        $activated = (new PendingState())->transitionOnStart();

        $session = $this->sessionRepository->createSession([
            'exam_id' => $examId,
            'enrollment_id' => $enrollment->enrollment_id,
            'candidate_user_id' => $candidateId,
            'tenant_id' => $exam->tenant_id,
            'session_state' => $activated->name(),
            'session_started_at' => now(),
        ]);

        return $this->toView($session);
    }

    public function getSession(string $sessionId): ExamSessionView
    {
        $session = $this->sessionRepository->findById($sessionId);

        if ($session === null) {
            throw new RuntimeException("Session {$sessionId} not found.");
        }

        return $this->toView($session);
    }

    public function submitResponse(SubmitResponseCommand $command): ExamSessionView
    {
        /** @var array{view: ExamSessionView, event: ResponseSubmitted} $result */
        $result = DB::transaction(function () use ($command): array {
            $session = $this->sessionRepository->findByIdForUpdate($command->sessionId);

            if ($session === null) {
                throw new RuntimeException("Session {$command->sessionId} not found.");
            }

            $this->assertTenantOwnership($session, $command);
            $this->assertCandidateOwnership($session, $command);

            $state = $this->stateFactory->fromSession($session);

            if (! $state->canSubmitResponse()) {
                throw InvalidSessionStateException::forOperation('submit_response', $state->name());
            }

            $item = $this->itemRepository->findByIdForUpdate($command->sessionItemId);

            if ($item === null || (string) $item->session_id !== $command->sessionId) {
                throw new RuntimeException(
                    "Session item {$command->sessionItemId} not found on session {$command->sessionId}."
                );
            }

            if ($command->expectedItemVersionLock !== null
                && (int) $item->version_lock !== $command->expectedItemVersionLock) {
                throw StaleVersionLockException::forSessionItem(
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

            return [
                'view' => $this->toView($session),
                'event' => $event,
            ];
        });

        event($result['event']);

        return $result['view'];
    }

    public function terminateSession(string $sessionId): ExamSessionView
    {
        /** @var array{session: CandidateExamStatus, event: ExamSessionCompleted} $result */
        $result = DB::transaction(function () use ($sessionId): array {
            $session = $this->sessionRepository->findByIdForUpdate($sessionId);

            if ($session === null) {
                throw new RuntimeException("Session {$sessionId} not found.");
            }

            $state = $this->stateFactory->fromSession($session);

            if (! $state->canTerminate()) {
                throw InvalidSessionStateException::forOperation('terminate', $state->name());
            }

            $next = $state->transitionOnTerminate();

            $session = $this->sessionRepository->updateSession($session, [
                'session_state' => $next->name(),
                'session_ended_at' => now(),
                'completion_method' => $next->name(),
            ]);

            $endedAt = $this->toDateTime($session->session_ended_at) ?? new DateTimeImmutable();

            $event = new ExamSessionCompleted(
                sessionId: (string) $session->session_id,
                tenantId: (string) $session->tenant_id,
                candidateId: (string) $session->candidate_user_id,
                examId: (string) $session->exam_id,
                finalState: $next->name(),
                completionMethod: (string) $session->completion_method,
                endedAt: $endedAt,
                totalQuestionsResponded: (int) $session->total_questions_responded,
                totalQuestionsFlagged: (int) $session->total_questions_flagged,
                versionLockAfter: (int) $session->version_lock,
            );

            return [
                'session' => $session,
                'event' => $event,
            ];
        });

        event($result['event']);

        return $this->toView($result['session']);
    }

    private function assertTenantOwnership(CandidateExamStatus $session, SubmitResponseCommand $command): void
    {
        if ((string) $session->tenant_id !== $command->tenantId) {
            throw new RuntimeException(
                "Tenant mismatch: session {$command->sessionId} does not belong to tenant {$command->tenantId}."
            );
        }
    }

    private function assertCandidateOwnership(CandidateExamStatus $session, SubmitResponseCommand $command): void
    {
        if ((string) $session->candidate_user_id !== $command->candidateId) {
            throw new RuntimeException(
                "Candidate {$command->candidateId} cannot submit a response on session {$command->sessionId}."
            );
        }
    }

    private function toView(CandidateExamStatus $session): ExamSessionView
    {
        return new ExamSessionView(
            sessionId: (string) $session->session_id,
            tenantId: (string) $session->tenant_id,
            examId: (string) $session->exam_id,
            candidateId: (string) $session->candidate_user_id,
            enrollmentId: (string) $session->enrollment_id,
            state: (string) $session->session_state,
            currentSessionItemId: null,
            currentQuestionVersionId: $session->current_question_reference,
            currentSectionId: null,
            currentQuestionIndex: (int) $session->current_question_index,
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

    /**
     * @return array<int, string>
     */
    private function nonTerminalStateNames(): array
    {
        $names = [];

        foreach ($this->allStateNames() as $name) {
            $state = $this->stateFactory->fromName($name);

            if (! $state->isTerminal()) {
                $names[] = $name;
            }
        }

        return $names;
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
}
