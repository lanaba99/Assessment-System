<?php

declare(strict_types=1);

namespace App\Domains\Proctoring\Exceptions;

use RuntimeException;

class SessionNotProctorableException extends RuntimeException
{
    public static function forTerminalState(string $sessionId, string $currentState): self
    {
        return new self(
            "Session [{$sessionId}] cannot accept proctoring events in state [{$currentState}]."
        );
    }
}
