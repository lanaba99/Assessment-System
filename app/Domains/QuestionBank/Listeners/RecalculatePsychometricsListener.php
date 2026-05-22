<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Listeners;

use App\Domains\ExamSession\Events\ExamSessionCompleted;
use App\Domains\QuestionBank\Jobs\CalculateQuestionMetricsJob;

class RecalculatePsychometricsListener
{
    public function handle(ExamSessionCompleted $event): void
    {
        CalculateQuestionMetricsJob::dispatch($event->sessionId);
    }
}
