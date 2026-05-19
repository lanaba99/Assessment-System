<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\States;

use App\Domains\ExamSession\Exceptions\InvalidSessionStateException;

final class SuspendedState implements ExamSessionState
{
    public const NAME = 'paused';

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
        return true;
    }

    public function canComplete(): bool
    {
        return false;
    }

    public function canTerminate(): bool
    {
        return true;
    }

    public function canRecordHeartbeat(): bool
    {
        return true;
    }

    public function isTerminal(): bool
    {
        return false;
    }

    public function transitionOnStart(): ExamSessionState
    {
        throw InvalidSessionStateException::forTransition($this->name(), ActiveState::NAME);
    }

    public function transitionOnSuspend(): ExamSessionState
    {
        throw InvalidSessionStateException::forTransition($this->name(), self::NAME);
    }

    public function transitionOnResume(): ExamSessionState
    {
        return new ActiveState();
    }

    public function transitionOnComplete(): ExamSessionState
    {
        throw InvalidSessionStateException::forTransition($this->name(), CompletedState::NAME);
    }

    public function transitionOnTerminate(): ExamSessionState
    {
        return new TerminatedState();
    }
}
