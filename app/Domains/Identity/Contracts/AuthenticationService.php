<?php

declare(strict_types=1);

namespace App\Domains\Identity\Contracts;

use App\Domains\Identity\DTOs\AuthenticationResult;

/**
 * Owns the login → session → logout lifecycle.
 * Collaborates with: UserRepository, UserSessionRepository, MfaDeviceRepository,
 * SecurityPolicyRepository, IpWhitelistRepository, plus a LoginAttempt sink for audit.
 */
interface AuthenticationService
{
    public function attemptLogin(
        string $tenantId,
        string $emailOrEmployeeId,
        string $plaintextPassword,
        string $ipAddress,
        string $userAgent,
    ): AuthenticationResult;

    public function verifyMfaForSession(
        string $tenantId,
        string $sessionId,
        string $oneTimeCode,
    ): AuthenticationResult;

    public function refreshSessionActivity(string $tenantId, string $sessionId): void;

    public function logout(string $tenantId, string $sessionId): void;

    public function revokeAllSessionsForUser(string $tenantId, string $userId): int;
}
