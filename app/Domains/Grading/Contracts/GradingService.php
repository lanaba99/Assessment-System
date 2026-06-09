<?php

declare(strict_types=1);

namespace App\Domains\Grading\Contracts;

use App\Domains\ExamSession\Events\ResponseSubmitted;
use App\Domains\Grading\Models\AnswerEvaluation;

interface GradingService
{
    /**
     * Grade a single candidate response and persist the AnswerEvaluation record.
     *
     * Called by ResponseSubmittedListener on the `grading` queue after each
     * response submission. Idempotent: re-grading the same response overwrites
     * the existing evaluation row rather than creating a duplicate.
     */
    public function gradeFromEvent(ResponseSubmitted $event): AnswerEvaluation;
}
