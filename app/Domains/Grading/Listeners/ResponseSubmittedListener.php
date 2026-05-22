<?php

declare(strict_types=1);

namespace App\Domains\Grading\Listeners;

use App\Domains\ExamSession\Events\ResponseSubmitted;
use App\Domains\Grading\Services\GradingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ResponseSubmittedListener implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'grading';

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        private readonly GradingService $gradingService,
    ) {
    }

    public function handle(ResponseSubmitted $event): void
    {
        $this->gradingService->gradeFromEvent($event);
    }
}
