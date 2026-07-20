<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Listeners;

use App\Domains\ExamSession\Events\ExamSessionCompleted;
use App\Domains\QuestionBank\Jobs\CalculateQuestionMetricsJob;
use App\Domains\QuestionBank\Services\PsychometricAnalysisService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;

/**
 * Queued listener that enqueues psychometric recalculation after a session ends.
 *
 * Previously synchronous — this caused two risks:
 *   1. The HTTP response was blocked while CalculateQuestionMetricsJob was
 *      being dispatched. If the queue backend was temporarily unavailable,
 *      the exception propagated back to the completeSession/terminateSession
 *      HTTP handler, returning a 500 for an operation that had already
 *      committed to the database.
 *   2. Any retry logic had to be handled inside the synchronous call.
 *
 * Running on the `psychometrics` queue groups all psychometric work together
 * and matches CalculateQuestionMetricsJob's own queue assignment. The backoff
 * mirrors the job's (30 s) to give transient queue failures time to resolve
 * before the dispatch is retried.
 *
 * Transaction safety: this listener is only invoked after ExamSessionCompleted
 * is fired, which happens outside the session-transition DB::transaction in
 * ExamSessionServiceImpl. The committed session data is therefore visible to
 * the job when it eventually runs.
 */
class RecalculatePsychometricsListener implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        private readonly PsychometricAnalysisService $analysisService,
    ) {
        $this->onQueue('psychometrics');
    }
    public function handle(ExamSessionCompleted $event): void
    {
        CalculateQuestionMetricsJob::dispatch($event->sessionId);
    }
}
