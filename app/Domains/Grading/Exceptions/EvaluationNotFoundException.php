<?php

declare(strict_types=1);

namespace App\Domains\Grading\Exceptions;

use RuntimeException;

class EvaluationNotFoundException extends RuntimeException
{
    public static function forId(string $evaluationId): self
    {
        return new self("Answer evaluation [{$evaluationId}] not found.");
    }
}
