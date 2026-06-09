<?php

declare(strict_types=1);

namespace App\Domains\Grading\Contracts;

use App\Domains\ExamSession\Events\ExamSessionCompleted;
use App\Domains\Grading\DTOs\AssessmentSummary;

interface AssessmentFinalizationService
{
    /**
     * Aggregate all AnswerEvaluations for the completed session into a Grade
     * and an AssessmentResult. Safe to call multiple times — each call overwrites
     * the previous provisional result (idempotent upsert).
     *
     * Fires ResultGenerated when the summary transitions to `final` status for
     * the first time (i.e. all pending_review items have been scored).
     */
    public function finalize(ExamSessionCompleted $event): AssessmentSummary;
}
