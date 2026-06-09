<?php

declare(strict_types=1);

namespace App\Domains\Grading\Exceptions;

use RuntimeException;

class InvalidScoreRangeException extends RuntimeException
{
    public static function outOfRange(float $awarded, float $maximum): self
    {
        return new self(
            "Score awarded ({$awarded}) must be between 0 and the maximum score possible ({$maximum})."
        );
    }
}
