<?php

declare(strict_types=1);

namespace App\Domains\Grading\Exceptions;

use RuntimeException;

class InvalidEvaluationStateException extends RuntimeException
{
    public static function notPendingReview(string $evaluationId, string $currentStatus): self
    {
        return new self(
            "Evaluation [{$evaluationId}] cannot be manually scored because its current status is '{$currentStatus}'. Only 'pending_review' evaluations can be scored."
        );
    }
}
