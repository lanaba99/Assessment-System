<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Contracts\AuthenticationService;
use App\Domains\Identity\Contracts\MfaService;
use App\Domains\Identity\Contracts\SecurityPolicyService;
use App\Domains\Identity\DTOs\AuthenticationResult;
use App\Domains\Identity\Exceptions\InvalidCredentialsException;
use App\Domains\Identity\Exceptions\InvalidInviteTokenException;
use App\Domains\Identity\Exceptions\IpNotAllowedException;
use App\Domains\Identity\Exceptions\MfaVerificationFailedException;
use App\Domains\Identity\Exceptions\PasswordPolicyViolationException;
use App\Domains\Identity\Exceptions\UserInactiveException;
use App\Domains\Identity\Models\SecurityPolicy;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Notifications\PasswordResetRequested;
use App\Domains\Identity\Repositories\IpWhitelistRepository;
use App\Domains\Identity\Repositories\LoginAttemptRepository;
use App\Domains\Identity\Repositories\MfaDeviceRepository;
use App\Domains\Identity\Repositories\PasswordResetTokenRepository;
use App\Domains\Identity\Repositories\SecurityPolicyRepository;
use App\Domains\Identity\Repositories\UserInvitationTokenRepository;
use App\Domains\Identity\Repositories\UserRepository;
use App\Domains\Identity\Repositories\UserSessionRepository;
use App\Http\Middleware\ThrottleLoginMiddleware;
use DateTimeImmutable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\IpUtils;

class AuthenticationServiceImpl implements AuthenticationService
{
    private const PASSWORD_RESET_TTL_MINUTES = 60;

    private const INVITE_TOKEN_TTL_DAYS = 7;

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
        private readonly PasswordResetTokenRepository $passwordResetTokens,
        private readonly UserInvitationTokenRepository $invitationTokens,
        private readonly SecurityPolicyService $securityPolicyService,
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
            $this->recordFailure($tenantId, $emailOrEmployeeId, $ipAddress, 'user_not_found', $userAgent);
            throw InvalidCredentialsException::forUnknownIdentifier();
        }

        if (! (bool) $user->is_active) {
            $this->recordFailure($tenantId, $emailOrEmployeeId, $ipAddress, 'user_inactive', $userAgent);
            throw UserInactiveException::forUser((string) $user->id);
        }

        if (! $this->hasher->check($plaintextPassword, (string) $user->password_hash)) {
            $this->recordFailure($tenantId, $emailOrEmployeeId, $ipAddress, 'invalid_password', $userAgent);
            throw InvalidCredentialsException::forWrongPassword();
        }

        if ($this->hasher->needsRehash((string) $user->password_hash)) {
            $this->users->update($user, [
                'password_hash' => $this->hasher->make($plaintextPassword),
            ]);
        }

        $policy = $this->policies->findActiveForTenant($tenantId);

        if ((bool) ($policy?->ip_whitelisting_enabled ?? false)
            && ! $this->ipIsPermitted($tenantId, $ipAddress, $policy)) {
            $this->recordFailure($tenantId, $emailOrEmployeeId, $ipAddress, 'ip_not_allowed', $userAgent);
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
                $this->clearRateLimiter($tenantId, (string) $user->email, $ipAddress);

                return new AuthenticationResult(
                    status: AuthenticationResult::STATUS_MFA_REQUIRED,
                    userId: (string) $user->id,
                    sessionId: (string) $session->session_id,
                    rejectionReason: null,
                    authenticatedAt: null,
                    mfaRequired: true,
                    user: $user,
                );
            }

            $this->users->update($user, ['last_login_at' => now()]);
            $this->loginAttempts->recordSuccess($tenantId, (string) $user->email, $ipAddress, $userAgent);
            $this->clearRateLimiter($tenantId, (string) $user->email, $ipAddress);

            return new AuthenticationResult(
                status: AuthenticationResult::STATUS_AUTHENTICATED,
                userId: (string) $user->id,
                sessionId: (string) $session->session_id,
                rejectionReason: null,
                authenticatedAt: new DateTimeImmutable(),
                mfaRequired: false,
                user: $user,
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
                user: $user,
            );
        });
    }

    public function refreshSessionActivity(string $tenantId, string $sessionId, ?string $userId = null): void
    {
        $session = $userId !== null
            ? $this->sessions->findForUser($tenantId, $userId, $sessionId)
            : $this->sessions->findById($tenantId, $sessionId);

        if ($session === null) {
            return;
        }

        $this->sessions->touchActivity($session);
    }

    public function logout(string $tenantId, string $sessionId, ?string $userId = null): void
    {
        $this->sessions->close($tenantId, $sessionId, $userId);
    }

    public function revokeAllSessionsForUser(string $tenantId, string $userId): int
    {
        return $this->sessions->revokeAllForUser($tenantId, $userId);
    }

    public function listSessionsForUser(string $tenantId, string $userId): array
    {
        return $this->sessions
            ->listActiveForUser($tenantId, $userId)
            ->map(static fn ($session): array => [
                'session_id' => (string) $session->session_id,
                'session_state' => (string) $session->session_state,
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'login_at' => $session->login_at?->toIso8601String(),
                'last_activity_at' => $session->last_activity_at?->toIso8601String(),
                'device_type' => $session->device_type,
                'browser_name' => $session->browser_name,
                'os_name' => $session->os_name,
            ])
            ->values()
            ->all();
    }

    public function revokeSessionForUser(string $tenantId, string $userId, string $sessionId): bool
    {
        return $this->sessions->revokeForUser($tenantId, $userId, $sessionId);
    }

    public function requestPasswordReset(string $tenantId, string $email): void
    {
        $user = $this->users->findByEmail($tenantId, $email);
        if ($user === null) {
            return;
        }

        $plainToken = Str::random(64);
        $this->passwordResetTokens->upsertToken((string) $user->email, $this->hasher->make($plainToken));

        // Dispatch via the user's `Notifiable` trait. With MAIL_MAILER=log
        // (dev/CI) the plaintext token is written to storage/logs/laravel.log;
        // with a real mailer (prod) it's emailed. The hash in the DB never
        // changes shape — only the delivery channel does.
        $user->notify(new PasswordResetRequested($plainToken, self::PASSWORD_RESET_TTL_MINUTES));
    }

    public function resetPasswordWithToken(string $tenantId, string $email, string $token, string $newPassword): bool
    {
        $user = $this->users->findByEmail($tenantId, $email);
        $stored = $this->passwordResetTokens->findByEmail($email);

        if ($user === null || $stored === null) {
            return false;
        }

        $isExpired = $stored->created_at === null
            || $stored->created_at->addMinutes(self::PASSWORD_RESET_TTL_MINUTES)->isPast();

        if ($isExpired || ! $this->hasher->check($token, (string) $stored->token)) {
            return false;
        }

        $validation = $this->securityPolicyService->validatePassword($tenantId, $newPassword);
        if (! $validation->passed) {
            throw new PasswordPolicyViolationException($validation->violations);
        }

        DB::transaction(function () use ($user, $tenantId, $email, $newPassword): void {
            $this->users->update($user, [
                'password_hash' => $this->hasher->make($newPassword),
            ]);
            $this->passwordResetTokens->deleteByEmail($email);

            // Compromised-credential containment: a successful reset must end
            // every prior session and revoke every issued bearer token, so an
            // attacker who knew the old password can't keep riding existing auth.
            $this->sessions->revokeAllForUser($tenantId, (string) $user->id);
            $user->tokens()->delete();
        });

        return true;
    }

    public function acceptInvite(string $tenantId, string $email, string $token, string $plaintextPassword): string
    {
        $user = $this->users->findByEmail($tenantId, $email);
        $stored = $this->invitationTokens->findByEmail($email);

        if ($user === null || $stored === null || (string) $stored->user_id !== (string) $user->id) {
            throw InvalidInviteTokenException::invalidOrExpired();
        }

        if ((string) $user->status !== 'pending' || (bool) $user->is_active) {
            throw InvalidInviteTokenException::userNotPending();
        }

        $isExpired = $stored->created_at === null
            || $stored->created_at->addDays(self::INVITE_TOKEN_TTL_DAYS)->isPast();

        if ($isExpired || ! $this->hasher->check($token, (string) $stored->token)) {
            throw InvalidInviteTokenException::invalidOrExpired();
        }

        $validation = $this->securityPolicyService->validatePassword($tenantId, $plaintextPassword);
        if (! $validation->passed) {
            throw new PasswordPolicyViolationException($validation->violations);
        }

        return DB::transaction(function () use ($user, $email, $plaintextPassword): string {
            $this->users->update($user, [
                'password_hash' => $this->hasher->make($plaintextPassword),
                'is_active' => true,
                'status' => 'active',
                'activated_at' => now(),
            ]);
            $this->invitationTokens->deleteByEmail($email);

            return (string) $user->id;
        });
    }

    private function resolveUser(string $tenantId, string $emailOrEmployeeId): ?User
    {
        return $this->users->findByEmail($tenantId, $emailOrEmployeeId)
            ?? $this->users->findByExternalEmployeeId($tenantId, $emailOrEmployeeId);
    }

    private function ipIsPermitted(string $tenantId, string $ipAddress, ?SecurityPolicy $policy): bool
    {
        if ($this->ipWhitelist->findMatchForIp($tenantId, $ipAddress) !== null) {
            return true;
        }

        $policyRanges = $policy?->allowed_ip_ranges;
        if (is_array($policyRanges) && $policyRanges !== []) {
            $normalized = array_values(array_filter(array_map(
                static fn ($range): string => trim((string) $range),
                $policyRanges,
            ), static fn (string $range): bool => $range !== ''));

            if ($normalized !== [] && IpUtils::checkIp($ipAddress, $normalized)) {
                return true;
            }
        }

        return false;
    }

    private function recordFailure(
        string $tenantId,
        string $emailAttempted,
        string $ipAddress,
        string $reason,
        string $userAgent,
    ): void {
        $this->loginAttempts->recordFailure($tenantId, $emailAttempted, $ipAddress, $reason, $userAgent);

        $emailKey = ThrottleLoginMiddleware::emailKey($tenantId, $emailAttempted);
        $ipKey = ThrottleLoginMiddleware::ipKey($tenantId, $ipAddress);

        if ($emailKey !== null) {
            RateLimiter::hit($emailKey, ThrottleLoginMiddleware::DECAY_SECONDS);
        }

        if ($ipKey !== null) {
            RateLimiter::hit($ipKey, ThrottleLoginMiddleware::DECAY_SECONDS);
        }
    }

    private function clearRateLimiter(string $tenantId, string $email, string $ipAddress): void
    {
        $emailKey = ThrottleLoginMiddleware::emailKey($tenantId, $email);
        $ipKey = ThrottleLoginMiddleware::ipKey($tenantId, $ipAddress);

        if ($emailKey !== null) {
            RateLimiter::clear($emailKey);
        }

        if ($ipKey !== null) {
            RateLimiter::clear($ipKey);
        }
    }
}
