<?php

declare(strict_types=1);

namespace App\Domains\Grading\Services;

use App\Domains\ExamSession\Events\ExamSessionCompleted;
use App\Domains\Grading\DTOs\AssessmentSummary;
use App\Domains\Grading\Events\ResultGenerated;
use App\Domains\Grading\Models\AnswerEvaluation;
use App\Domains\Grading\Repositories\AnswerEvaluationRepository;
use App\Domains\Grading\Repositories\AssessmentResultRepository;
use App\Domains\Grading\Repositories\GradeRepository;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AssessmentFinalizationService
{
    private const PASS_THRESHOLD_PERCENT = 60.0;

    private const PENDING_STATUS = 'pending_review';

    public function __construct(
        private readonly AnswerEvaluationRepository $evaluations,
        private readonly GradeRepository $grades,
        private readonly AssessmentResultRepository $results,
    ) {
    }

    public function finalize(ExamSessionCompleted $event): AssessmentSummary
    {
        /** @var array{summary: AssessmentSummary, shouldFire: bool} $outcome */
        $outcome = DB::transaction(function () use ($event): array {
            $evaluations = $this->evaluations->findBySession($event->sessionId);

            $summary = $this->aggregate($evaluations, $event);

            $existing = $this->results->findBySession($event->sessionId);
            $wasFinalBefore = $existing?->result_status === AssessmentSummary::STATUS_FINAL;

            $this->grades->upsertFromSummary($summary);
            $this->results->upsertFromSummary($summary);

            $shouldFire = $summary->isFinal && ! $wasFinalBefore;

            return [
                'summary' => $summary,
                'shouldFire' => $shouldFire,
            ];
        });

        if ($outcome['shouldFire']) {
            event(new ResultGenerated(
                summary: $outcome['summary'],
                isFirstFinalization: true,
                calculatedAt: new DateTimeImmutable(),
            ));
        }

        return $outcome['summary'];
    }

    /**
     * @param  Collection<int, AnswerEvaluation>  $evaluations
     */
    private function aggregate(Collection $evaluations, ExamSessionCompleted $event): AssessmentSummary
    {
        $rawScore = 0.0;
        $maxScore = 0.0;
        $pending = 0;
        $correct = 0;
        $incorrect = 0;
        $breakdown = [];

        foreach ($evaluations as $eval) {
            $awarded = (float) $eval->score_awarded;
            $max = (float) $eval->max_score_possible;

            $rawScore += $awarded;
            $maxScore += $max;

            if ($eval->evaluation_status === self::PENDING_STATUS) {
                $pending++;
            }

            $metadata = is_array($eval->evaluation_metadata) ? $eval->evaluation_metadata : [];
            $isCorrect = $metadata['is_correct'] ?? null;

            if ($isCorrect === true) {
                $correct++;
            } elseif ($isCorrect === false) {
                $incorrect++;
            }

            $breakdown[] = [
                'question_id' => $eval->question_id,
                'score_awarded' => $awarded,
                'max_score_possible' => $max,
                'evaluation_status' => $eval->evaluation_status,
                'evaluation_type' => $eval->evaluation_type,
                'is_correct' => $isCorrect,
            ];
        }

        $percentage = $maxScore > 0.0 ? round(($rawScore / $maxScore) * 100.0, 2) : 0.0;
        $total = $evaluations->count();
        $isFinal = $pending === 0 && $total > 0;

        return new AssessmentSummary(
            sessionId: $event->sessionId,
            candidateId: $event->candidateId,
            examId: $event->examId,
            tenantId: $event->tenantId,
            rawScore: round($rawScore, 2),
            maxScore: round($maxScore, 2),
            percentage: $percentage,
            gradeLetter: $this->letterFor($percentage, $maxScore > 0.0),
            isPassing: $percentage >= self::PASS_THRESHOLD_PERCENT && $maxScore > 0.0,
            isFinal: $isFinal,
            totalEvaluations: $total,
            pendingEvaluations: $pending,
            correctCount: $correct,
            incorrectCount: $incorrect,
            breakdown: $breakdown,
        );
    }

    private function letterFor(float $percentage, bool $hasScorableEvaluations): string
    {
        if (! $hasScorableEvaluations) {
            return 'N/A';
        }

        return match (true) {
            $percentage >= 90.0 => 'A',
            $percentage >= 80.0 => 'B',
            $percentage >= 70.0 => 'C',
            $percentage >= 60.0 => 'D',
            default => 'F',
        };
    }
}
