<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\States;

use App\Domains\ExamSession\Exceptions\InvalidSessionStateException;

final class TerminatedState implements ExamSessionState
{
    public const NAME = 'terminated';

    public function name(): string
    {
        return self::NAME;
    }

    public function canStart(): bool
    {
        return false;
    }

    public function canSubmitResponse(): bool
    {
        return false;
    }

    public function canSuspend(): bool
    {
        return false;
    }

    public function canResume(): bool
    {
        return false;
    }

    public function canComplete(): bool
    {
        return false;
    }

    public function canTerminate(): bool
    {
        return false;
    }

    public function canRecordHeartbeat(): bool
    {
        return false;
    }

    public function isTerminal(): bool
    {
        return true;
    }

    public function transitionOnStart(): ExamSessionState
    {
        throw InvalidSessionStateException::forTransition($this->name(), ActiveState::NAME);
    }

    public function transitionOnSuspend(): ExamSessionState
    {
        throw InvalidSessionStateException::forTransition($this->name(), SuspendedState::NAME);
    }

    public function transitionOnResume(): ExamSessionState
    {
        throw InvalidSessionStateException::forTransition($this->name(), ActiveState::NAME);
    }

    public function transitionOnComplete(): ExamSessionState
    {
        throw InvalidSessionStateException::forTransition($this->name(), CompletedState::NAME);
    }

    public function transitionOnTerminate(): ExamSessionState
    {
        throw InvalidSessionStateException::forTransition($this->name(), self::NAME);
    }
}
