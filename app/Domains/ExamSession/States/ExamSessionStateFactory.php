<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\States;

use App\Domains\ExamSession\Exceptions\InvalidSessionStateException;
use App\Domains\ExamSession\Models\CandidateExamStatus;

class ExamSessionStateFactory
{
    public function fromName(string $stateName): ExamSessionState
    {
        return match ($stateName) {
            PendingState::NAME => new PendingState(),
            ActiveState::NAME => new ActiveState(),
            SuspendedState::NAME => new SuspendedState(),
            CompletedState::NAME => new CompletedState(),
            TerminatedState::NAME => new TerminatedState(),
            default => throw InvalidSessionStateException::forUnknownState($stateName),
        };
    }

    public function fromSession(CandidateExamStatus $session): ExamSessionState
    {
        return $this->fromName($session->session_state);
    }
}
