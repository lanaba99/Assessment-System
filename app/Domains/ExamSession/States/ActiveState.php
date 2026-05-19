<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\States;

use App\Domains\ExamSession\Exceptions\InvalidSessionStateException;

final class ActiveState implements ExamSessionState
{
    public const NAME = 'in_progress';

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
        return true;
    }

    public function canSuspend(): bool
    {
        return true;
    }

    public function canResume(): bool
    {
        return false;
    }

    public function canComplete(): bool
    {
        return true;
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
        throw InvalidSessionStateException::forTransition($this->name(), self::NAME);
    }

    public function transitionOnSuspend(): ExamSessionState
    {
        return new SuspendedState();
    }

    public function transitionOnResume(): ExamSessionState
    {
        throw InvalidSessionStateException::forTransition($this->name(), self::NAME);
    }

    public function transitionOnComplete(): ExamSessionState
    {
        return new CompletedState();
    }

    public function transitionOnTerminate(): ExamSessionState
    {
        return new TerminatedState();
    }
}
