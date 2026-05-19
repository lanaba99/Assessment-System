<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\Repositories;

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
        return $this->session->newQuery()->create($attributes);
    }

    public function recordResponse(array $attributes): QuestionResponse
    {
        return $this->response->newQuery()->create($attributes);
    }

    public function updateSession(CandidateExamStatus $session, array $attributes): CandidateExamStatus
    {
        $session->fill($attributes)->save();

        return $session;
    }
}
