<?php

declare(strict_types=1);

namespace App\Domains\Grading\Contracts;

use App\Domains\Grading\DTOs\SubmitEvaluationCommand;
use App\Domains\Grading\Exceptions\EvaluationNotFoundException;
use App\Domains\Grading\Exceptions\InvalidEvaluationStateException;
use App\Domains\Grading\Exceptions\InvalidScoreRangeException;
use App\Domains\Grading\Models\AnswerEvaluation;
use Illuminate\Support\Collection;

interface ManualEvaluationService
{
    /**
     * Record a human evaluator's score for a pending_review evaluation.
     *
     * Transition: pending_review → scored (manual).
     *
     * After the score is written, checks whether all evaluations for the session
     * are now in a terminal status. If so, re-triggers AssessmentFinalizationService
     * so the final Grade and AssessmentResult incorporate the newly submitted score.
     *
     * @throws EvaluationNotFoundException     when the evaluation does not exist for this tenant
     * @throws InvalidEvaluationStateException when the evaluation is not in pending_review status
     * @throws InvalidScoreRangeException      when scoreAwarded < 0 or > max_score_possible
     */
    public function submitScore(SubmitEvaluationCommand $command): AnswerEvaluation;

    /**
     * Return all evaluations for a session that are still awaiting human review.
     *
     * @return Collection<int, AnswerEvaluation>
     */
    public function listPendingForSession(string $tenantId, string $sessionId): Collection;
}
