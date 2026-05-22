<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\Repositories;

use App\Domains\ExamSession\Exceptions\StaleVersionLockException;
use App\Domains\ExamSession\Models\CandidateExamStatus;
use App\Domains\ExamSession\Models\ExamCandidateEligible;
use App\Domains\ExamSession\Models\QuestionResponse;
use Illuminate\Support\Collection;

class SessionRepository
{
    public function __construct(
        private readonly CandidateExamStatus $session,
        private readonly QuestionResponse $response,
        private readonly ExamCandidateEligible $enrollment,
    ) {
    }

    public function findActiveSession(string $candidateId, string $examId, array $activeStates): ?CandidateExamStatus
    {
        return $this->session
            ->newQuery()
            ->where('candidate_user_id', $candidateId)
            ->where('exam_id', $examId)
            ->whereIn('session_state', $activeStates)
            ->whereNull('session_ended_at')
            ->first();
    }

    public function getAnswersSnapshot(string $sessionId): Collection
    {
        return $this->response
            ->newQuery()
            ->where('session_id', $sessionId)
            ->orderBy('question_sequence_number')
            ->get();
    }

    public function findById(string $sessionId): ?CandidateExamStatus
    {
        return $this->session->newQuery()->find($sessionId);
    }

    /**
     * Pessimistic lock — must be called inside an active DB transaction.
     */
    public function findByIdForUpdate(string $sessionId): ?CandidateExamStatus
    {
        return $this->session
            ->newQuery()
            ->where('session_id', $sessionId)
            ->lockForUpdate()
            ->first();
    }

    public function findEnrollment(string $candidateId, string $examId): ?ExamCandidateEligible
    {
        return $this->enrollment
            ->newQuery()
            ->where('candidate_user_id', $candidateId)
            ->where('exam_id', $examId)
            ->first();
    }

    public function createSession(array $attributes): CandidateExamStatus
    {
        $attributes['version_lock'] = $attributes['version_lock'] ?? 0;

        return $this->session->newQuery()->create($attributes);
    }

    public function recordResponse(array $attributes): QuestionResponse
    {
        return $this->response->newQuery()->create($attributes);
    }

    /**
     * Optimistic update — compares the in-memory `version_lock` against the
     * persisted row and atomically increments it. Throws if another process
     * has modified the row since this instance was loaded.
     */
    public function updateSession(CandidateExamStatus $session, array $attributes): CandidateExamStatus
    {
        $expected = (int) $session->version_lock;
        $next = $expected + 1;

        $payload = array_merge($attributes, ['version_lock' => $next]);

        $affected = $this->session
            ->newQuery()
            ->where('session_id', $session->session_id)
            ->where('version_lock', $expected)
            ->update($payload);

        if ($affected === 0) {
            throw StaleVersionLockException::forSession((string) $session->session_id, $expected);
        }

        $session->forceFill($payload);
        $session->syncOriginal();

        return $session;
    }
}
