<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\Exceptions;

use RuntimeException;

class InvalidSessionStateException extends RuntimeException
{
    public static function forOperation(string $operation, string $currentState): self
    {
        return new self("Operation '{$operation}' is not allowed while session is in state '{$currentState}'.");
    }

    public static function forTransition(string $from, string $to): self
    {
        return new self("Illegal state transition from '{$from}' to '{$to}'.");
    }

    public static function forUnknownState(string $stateName): self
    {
        return new self("Unknown session state: '{$stateName}'.");
    }
}
