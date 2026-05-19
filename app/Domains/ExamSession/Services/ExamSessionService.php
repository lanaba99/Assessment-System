<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\Services;

use App\Domains\ExamEngine\Repositories\ExamRepository;
use App\Domains\ExamSession\Models\CandidateExamStatus;
use App\Domains\ExamSession\Models\QuestionResponse;
use App\Domains\ExamSession\Repositories\SessionRepository;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExamSessionService
{
    private const ACTIVE_STATES = ['not_started', 'in_progress', 'paused'];

    private const SUBMITTABLE_STATES = ['in_progress', 'paused'];

    private const STATE_IN_PROGRESS = 'in_progress';

    private const STATE_TERMINATED = 'terminated';

    public function __construct(
        private readonly ExamRepository $examRepository,
        private readonly SessionRepository $sessionRepository,
    ) {
    }

    public function startSession(string $candidateId, string $examId): CandidateExamStatus
    {
        $exam = $this->examRepository->findWithSectionsAndQuestions($examId);

        if ($exam === null) {
            throw new RuntimeException("Exam {$examId} not found.");
        }

        $active = $this->sessionRepository->findActiveSession($candidateId, $examId, self::ACTIVE_STATES);

        if ($active !== null) {
            return $active;
        }

        $enrollment = $this->sessionRepository->findEnrollment($candidateId, $examId);

        if ($enrollment === null) {
            throw new RuntimeException("Candidate {$candidateId} is not enrolled in exam {$examId}.");
        }

        return $this->sessionRepository->createSession([
            'exam_id' => $examId,
            'enrollment_id' => $enrollment->enrollment_id,
            'candidate_user_id' => $candidateId,
            'tenant_id' => $exam->tenant_id,
            'session_state' => self::STATE_IN_PROGRESS,
            'session_started_at' => now(),
        ]);
    }

    public function submitResponse(string $sessionId, string $questionVersionId, array $payload): QuestionResponse
    {
        return DB::transaction(function () use ($sessionId, $questionVersionId, $payload): QuestionResponse {
            $session = $this->sessionRepository->findById($sessionId);

            if ($session === null) {
                throw new RuntimeException("Session {$sessionId} not found.");
            }

            if (! in_array($session->session_state, self::SUBMITTABLE_STATES, true)) {
                throw new RuntimeException("Session {$sessionId} is not active.");
            }

            $response = $this->sessionRepository->recordResponse(array_merge($payload, [
                'session_id' => $sessionId,
                'question_version_id' => $questionVersionId,
                'candidate_user_id' => $session->candidate_user_id,
                'tenant_id' => $session->tenant_id,
                'response_submitted_at' => now(),
            ]));

            $this->sessionRepository->updateSession($session, [
                'total_questions_responded' => $session->total_questions_responded + 1,
            ]);

            return $response;
        });
    }

    public function terminateSession(string $sessionId): CandidateExamStatus
    {
        $session = $this->sessionRepository->findById($sessionId);

        if ($session === null) {
            throw new RuntimeException("Session {$sessionId} not found.");
        }

        return $this->sessionRepository->updateSession($session, [
            'session_state' => self::STATE_TERMINATED,
            'session_ended_at' => now(),
            'completion_method' => self::STATE_TERMINATED,
        ]);
    }
}
