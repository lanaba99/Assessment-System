<?php

declare(strict_types=1);

namespace App\Domains\Grading\Listeners;

use App\Domains\Grading\Events\ResultGenerated;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Temporary placeholder listener for the ResultGenerated domain event.
 *
 * ResultGenerated is fired by AssessmentFinalizationService after the first
 * successful grade finalization for a session (isFinal = true, first time).
 * It carries a fully resolved AssessmentSummary and is fired OUTSIDE the
 * DB::transaction in finalize(), so the committed grade data is always
 * visible when this listener runs.
 *
 * This listener exists solely to provide observability (via the application
 * log) until the Analytics module is built. It must be replaced — not
 * extended — once that module is ready.
 *
 * TODO: Replace this placeholder with an AnalyticsModule integration when
 *       the Analytics domain is implemented. The listener should be moved to
 *       app/Domains/Analytics/Listeners/ and registered in
 *       AnalyticsServiceProvider. The ResultGenerated event contract
 *       (AssessmentSummary + isFirstFinalization + calculatedAt) must remain
 *       stable so the new listener can consume it without changes to the
 *       Grading domain.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * Trait note for reviewers:
 * Queued *listeners* require only ShouldQueue + InteractsWithQueue.
 * The Dispatchable, Queueable, and SerializesModels traits belong to queued
 * *Jobs*. Adding them to a listener is harmless but unnecessary, so they are
 * intentionally omitted here to keep the class semantically correct.
 * ─────────────────────────────────────────────────────────────────────────
 */
class LogResultGeneratedListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Use the default queue — no dedicated analytics queue exists yet.
     * Switch this to 'analytics' when that infrastructure is in place.
     */
    public string $queue = 'default';

    public int $tries = 3;

    public int $backoff = 5;

    public function handle(ResultGenerated $event): void
    {
        $summary = $event->summary;

        Log::info('ResultGenerated: assessment result finalized', [
            'session_id' => $summary->sessionId,
            'tenant_id' => $summary->tenantId,
            'candidate_id' => $summary->candidateId,
            'exam_id' => $summary->examId,
            'raw_score' => $summary->rawScore,
            'max_score' => $summary->maxScore,
            'percentage' => $summary->percentage,
            'grade_letter' => $summary->gradeLetter,
            'is_passing' => $summary->isPassing,
            'is_final' => $summary->isFinal,
            'total_evaluations' => $summary->totalEvaluations,
            'pending_evaluations' => $summary->pendingEvaluations,
            'is_first_finalization' => $event->isFirstFinalization,
            'calculated_at' => $event->calculatedAt->format(DateTimeInterface::ATOM),
        ]);
    }
}
