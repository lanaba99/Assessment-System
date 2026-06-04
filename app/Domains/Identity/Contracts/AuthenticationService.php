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

    public function refreshSessionActivity(string $tenantId, string $sessionId, ?string $userId = null): void;

    public function logout(string $tenantId, string $sessionId, ?string $userId = null): void;

    public function revokeAllSessionsForUser(string $tenantId, string $userId): int;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listSessionsForUser(string $tenantId, string $userId): array;

    public function revokeSessionForUser(string $tenantId, string $userId, string $sessionId): bool;

    public function requestPasswordReset(string $tenantId, string $email): void;

    public function resetPasswordWithToken(string $tenantId, string $email, string $token, string $newPassword): bool;

    /**
     * Activates a pending invited user after token + password validation.
     *
     * @return string Activated user id.
     */
    public function acceptInvite(string $tenantId, string $email, string $token, string $plaintextPassword): string;
}
