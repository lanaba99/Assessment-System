<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Contracts\AuthenticationService;
use App\Domains\Identity\Contracts\MfaService;
use App\Domains\Identity\DTOs\AuthenticationResult;
use App\Domains\Identity\Exceptions\InvalidCredentialsException;
use App\Domains\Identity\Exceptions\IpNotAllowedException;
use App\Domains\Identity\Exceptions\MfaVerificationFailedException;
use App\Domains\Identity\Exceptions\UserInactiveException;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Repositories\IpWhitelistRepository;
use App\Domains\Identity\Repositories\LoginAttemptRepository;
use App\Domains\Identity\Repositories\MfaDeviceRepository;
use App\Domains\Identity\Repositories\SecurityPolicyRepository;
use App\Domains\Identity\Repositories\UserRepository;
use App\Domains\Identity\Repositories\UserSessionRepository;
use DateTimeImmutable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\DB;

class AuthenticationServiceImpl implements AuthenticationService
{
    private const SESSION_STATE_PENDING_MFA = 'pending_mfa';

    private const SESSION_STATE_ACTIVE = 'active';

    public function __construct(
        private readonly UserRepository $users,
        private readonly UserSessionRepository $sessions,
        private readonly MfaDeviceRepository $mfaDevices,
        private readonly IpWhitelistRepository $ipWhitelist,
        private readonly SecurityPolicyRepository $policies,
        private readonly LoginAttemptRepository $loginAttempts,
        private readonly MfaService $mfaService,
        private readonly Hasher $hasher,
    ) {
    }

    public function attemptLogin(
        string $tenantId,
        string $emailOrEmployeeId,
        string $plaintextPassword,
        string $ipAddress,
        string $userAgent,
    ): AuthenticationResult {
        // Validation runs OUTSIDE the transaction. Failure-path audit writes
        // (recordFailure) must survive even when we throw — wrapping them in
        // a transaction that the same throw rolls back wipes the audit trail
        // exactly when investigators need it most.
        $user = $this->resolveUser($tenantId, $emailOrEmployeeId);

        if ($user === null) {
            $this->loginAttempts->recordFailure($tenantId, $emailOrEmployeeId, $ipAddress, 'user_not_found', $userAgent);
            throw InvalidCredentialsException::forUnknownIdentifier();
        }

        if (! (bool) $user->is_active) {
            $this->loginAttempts->recordFailure($tenantId, $emailOrEmployeeId, $ipAddress, 'user_inactive', $userAgent);
            throw UserInactiveException::forUser((string) $user->id);
        }

        if (! $this->hasher->check($plaintextPassword, (string) $user->password_hash)) {
            $this->loginAttempts->recordFailure($tenantId, $emailOrEmployeeId, $ipAddress, 'invalid_password', $userAgent);
            throw InvalidCredentialsException::forWrongPassword();
        }

        if ($this->hasher->needsRehash((string) $user->password_hash)) {
            $this->users->update($user, [
                'password_hash' => $this->hasher->make($plaintextPassword),
            ]);
        }

        $policy = $this->policies->findActiveForTenant($tenantId);

        if ((bool) ($policy?->ip_whitelisting_enabled ?? false)
            && $this->ipWhitelist->findExactMatch($tenantId, $ipAddress) === null) {
            $this->loginAttempts->recordFailure($tenantId, $emailOrEmployeeId, $ipAddress, 'ip_not_allowed', $userAgent);
            throw IpNotAllowedException::forIp($ipAddress);
        }

        $mfaRequiredByPolicy = (bool) ($policy?->mfa_enabled ?? false);
        $userHasVerifiedDevice = $this->mfaDevices
            ->listVerifiedForUser($tenantId, (string) $user->id)
            ->isNotEmpty();

        $challengeMfa = $mfaRequiredByPolicy && $userHasVerifiedDevice;

        // Success path: session creation + success-audit + last_login update
        // are atomically related — all or none.
        return DB::transaction(function () use ($user, $tenantId, $ipAddress, $userAgent, $challengeMfa): AuthenticationResult {
            $session = $this->sessions->create($tenantId, (string) $user->id, [
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'session_state' => $challengeMfa ? self::SESSION_STATE_PENDING_MFA : self::SESSION_STATE_ACTIVE,
            ]);

            if ($challengeMfa) {
                $this->loginAttempts->recordSuccess($tenantId, (string) $user->email, $ipAddress, $userAgent);

                return new AuthenticationResult(
                    status: AuthenticationResult::STATUS_MFA_REQUIRED,
                    userId: (string) $user->id,
                    sessionId: (string) $session->session_id,
                    rejectionReason: null,
                    authenticatedAt: null,
                    mfaRequired: true,
                );
            }

            $this->users->update($user, ['last_login_at' => now()]);
            $this->loginAttempts->recordSuccess($tenantId, (string) $user->email, $ipAddress, $userAgent);

            return new AuthenticationResult(
                status: AuthenticationResult::STATUS_AUTHENTICATED,
                userId: (string) $user->id,
                sessionId: (string) $session->session_id,
                rejectionReason: null,
                authenticatedAt: new DateTimeImmutable(),
                mfaRequired: false,
            );
        });
    }

    public function verifyMfaForSession(
        string $tenantId,
        string $sessionId,
        string $oneTimeCode,
    ): AuthenticationResult {
        return DB::transaction(function () use ($tenantId, $sessionId, $oneTimeCode): AuthenticationResult {
            $session = $this->sessions->findById($tenantId, $sessionId);

            if ($session === null || (string) $session->session_state !== self::SESSION_STATE_PENDING_MFA) {
                throw MfaVerificationFailedException::sessionNotEligible($sessionId);
            }

            $verified = $this->mfaService->verifyToken($tenantId, (string) $session->user_id, $oneTimeCode);

            if (! $verified) {
                throw MfaVerificationFailedException::invalidCode();
            }

            $session->fill([
                'session_state' => self::SESSION_STATE_ACTIVE,
                'last_activity_at' => now(),
            ])->save();

            $user = $this->users->findById($tenantId, (string) $session->user_id);

            if ($user !== null) {
                $this->users->update($user, ['last_login_at' => now()]);
            }

            return new AuthenticationResult(
                status: AuthenticationResult::STATUS_AUTHENTICATED,
                userId: (string) $session->user_id,
                sessionId: (string) $session->session_id,
                rejectionReason: null,
                authenticatedAt: new DateTimeImmutable(),
                mfaRequired: false,
            );
        });
    }

    public function refreshSessionActivity(string $tenantId, string $sessionId): void
    {
        $session = $this->sessions->findById($tenantId, $sessionId);

        if ($session === null) {
            return;
        }

        $this->sessions->touchActivity($session);
    }

    public function logout(string $tenantId, string $sessionId): void
    {
        $this->sessions->close($tenantId, $sessionId);
    }

    public function revokeAllSessionsForUser(string $tenantId, string $userId): int
    {
        return $this->sessions->revokeAllForUser($tenantId, $userId);
    }

    private function resolveUser(string $tenantId, string $emailOrEmployeeId): ?User
    {
        return $this->users->findByEmail($tenantId, $emailOrEmployeeId)
            ?? $this->users->findByExternalEmployeeId($tenantId, $emailOrEmployeeId);
    }
}
