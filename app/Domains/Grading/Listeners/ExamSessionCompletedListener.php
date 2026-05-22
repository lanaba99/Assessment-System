<?php

declare(strict_types=1);

namespace App\Domains\Grading\Listeners;

use App\Domains\ExamSession\Events\ExamSessionCompleted;
use App\Domains\Grading\Services\AssessmentFinalizationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ExamSessionCompletedListener implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'grading';

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        private readonly AssessmentFinalizationService $finalizer,
    ) {
    }

    public function handle(ExamSessionCompleted $event): void
    {
        $this->finalizer->finalize($event);
    }
}
