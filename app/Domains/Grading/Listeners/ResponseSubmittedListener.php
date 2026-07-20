<?php

declare(strict_types=1);

namespace App\Domains\Grading\Listeners;

use App\Domains\ExamSession\Events\ResponseSubmitted;
use App\Domains\Grading\Contracts\GradingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;

class ResponseSubmittedListener implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        private readonly GradingService $gradingService,
    ) {
        $this->onQueue('grading');
    }

    public function handle(ResponseSubmitted $event): void
    {
        $this->gradingService->gradeFromEvent($event);
    }
}
