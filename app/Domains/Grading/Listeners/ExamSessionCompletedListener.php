<?php

declare(strict_types=1);

namespace App\Domains\Grading\Listeners;

use App\Domains\ExamSession\Events\ExamSessionCompleted;
use App\Domains\Grading\Contracts\AssessmentFinalizationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;

class ExamSessionCompletedListener implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        private readonly AssessmentFinalizationService $finalizer,
    ) {
        $this->onQueue('grading');
    }

    public function handle(ExamSessionCompleted $event): void
    {
        $this->finalizer->finalize($event);
    }
}
