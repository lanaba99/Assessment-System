<?php

declare(strict_types=1);

namespace App\Domains\ExamEngine\Exceptions;

use RuntimeException;

class InvalidExamStateException extends RuntimeException
{
    public static function forOperation(string $operation, string $currentState): self
    {
        return new self("Operation '{$operation}' is not allowed while exam is in state '{$currentState}'.");
    }
}
