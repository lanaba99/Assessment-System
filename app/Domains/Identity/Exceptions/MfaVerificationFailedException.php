<?php

declare(strict_types=1);

namespace App\Domains\Identity\Exceptions;

class MfaVerificationFailedException extends AuthenticationFailedException
{
    public static function invalidCode(): self
    {
        return new self('The provided MFA code is invalid or expired.', 'mfa_invalid_code');
    }

    public static function sessionNotEligible(string $sessionId): self
    {
        return new self("Session {$sessionId} is not awaiting MFA verification.", 'mfa_session_not_eligible');
    }
}
