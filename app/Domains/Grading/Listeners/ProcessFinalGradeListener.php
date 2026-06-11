<?php

declare(strict_types=1);

namespace App\Domains\Grading\Listeners;

use App\Domains\Grading\Events\ResultGenerated;
use App\Domains\Grading\Services\FinalGradeProcessingService;

class ProcessFinalGradeListener
{
    public function __construct(
        private readonly FinalGradeProcessingService $processor,
    ) {
    }

    public function handle(ResultGenerated $event): void
    {
        if (! $event->isFirstFinalization || ! $event->summary->isFinal) {
            return;
        }

        $this->processor->process(
            tenantId: $event->summary->tenantId,
            sessionId: $event->summary->sessionId,
        );
    }
}
