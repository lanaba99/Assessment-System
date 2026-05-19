<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\States;

interface ExamSessionState
{
    public function name(): string;

    public function canStart(): bool;

    public function canSubmitResponse(): bool;

    public function canSuspend(): bool;

    public function canResume(): bool;

    public function canComplete(): bool;

    public function canTerminate(): bool;

    public function canRecordHeartbeat(): bool;

    public function isTerminal(): bool;

    public function transitionOnStart(): self;

    public function transitionOnSuspend(): self;

    public function transitionOnResume(): self;

    public function transitionOnComplete(): self;

    public function transitionOnTerminate(): self;
}
