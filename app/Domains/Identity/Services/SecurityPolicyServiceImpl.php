<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Contracts\SecurityPolicyService;
use App\Domains\Identity\DTOs\PasswordValidationResult;
use App\Domains\Identity\Models\SecurityPolicy;
use App\Domains\Identity\Repositories\SecurityPolicyRepository;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SecurityPolicyServiceImpl implements SecurityPolicyService
{
    private const UPDATABLE_FIELDS = [
        'mfa_enabled',
        'mfa_method',
        'password_min_length',
        'password_require_uppercase',
        'password_require_lowercase',
        'password_require_numbers',
        'password_require_special_chars',
        'password_expiry_days',
        'password_history_count',
        'session_timeout_minutes',
        'session_absolute_timeout_hours',
        'session_force_reauth_on_privilege_change',
        'ip_whitelisting_enabled',
        'enable_biometric_auth',
        'enforce_tls_1_3_minimum',
        'disable_weak_ciphers',
        'allowed_ip_ranges',
    ];

    public function __construct(
        private readonly SecurityPolicyRepository $policies,
    ) {
    }

    public function getActivePolicy(string $tenantId): ?SecurityPolicy
    {
        return $this->policies->findActiveForTenant($tenantId);
    }

    public function updatePolicy(string $tenantId, array $changes, string $updatedByUserId): SecurityPolicy
    {
        return DB::transaction(function () use ($tenantId, $changes, $updatedByUserId): SecurityPolicy {
            $policy = $this->policies->findActiveForTenant($tenantId);

            if ($policy === null) {
                throw new RuntimeException("No active security policy for tenant {$tenantId}.");
            }

            $sanitized = array_intersect_key($changes, array_flip(self::UPDATABLE_FIELDS));

            if ($sanitized === []) {
                return $policy;
            }

            $sanitized['created_by_user_id'] = $updatedByUserId;

            return $this->policies->update($policy, $sanitized);
        });
    }

    public function validatePassword(string $tenantId, string $plaintextPassword): PasswordValidationResult
    {
        $policy = $this->policies->findActiveForTenant($tenantId);

        if ($policy === null) {
            return PasswordValidationResult::passed();
        }

        $violations = [];

        $minLength = max(1, (int) $policy->password_min_length);
        if (strlen($plaintextPassword) < $minLength) {
            $violations[] = "Password must be at least {$minLength} characters.";
        }

        if ((bool) $policy->password_require_uppercase && ! preg_match('/[A-Z]/', $plaintextPassword)) {
            $violations[] = 'Password must contain at least one uppercase letter.';
        }

        if ((bool) $policy->password_require_lowercase && ! preg_match('/[a-z]/', $plaintextPassword)) {
            $violations[] = 'Password must contain at least one lowercase letter.';
        }

        if ((bool) $policy->password_require_numbers && ! preg_match('/\d/', $plaintextPassword)) {
            $violations[] = 'Password must contain at least one number.';
        }

        if ((bool) $policy->password_require_special_chars && ! preg_match('/[^A-Za-z0-9]/', $plaintextPassword)) {
            $violations[] = 'Password must contain at least one special character.';
        }

        return $violations === []
            ? PasswordValidationResult::passed()
            : PasswordValidationResult::failed($violations);
    }

    public function isMfaRequired(string $tenantId): bool
    {
        return (bool) ($this->policies->findActiveForTenant($tenantId)?->mfa_enabled ?? false);
    }

    public function isIpWhitelistingEnabled(string $tenantId): bool
    {
        return (bool) ($this->policies->findActiveForTenant($tenantId)?->ip_whitelisting_enabled ?? false);
    }

    public function sessionIdleTimeoutMinutes(string $tenantId): int
    {
        return (int) ($this->policies->findActiveForTenant($tenantId)?->session_timeout_minutes ?? 30);
    }

    public function sessionAbsoluteTimeoutHours(string $tenantId): int
    {
        return (int) ($this->policies->findActiveForTenant($tenantId)?->session_absolute_timeout_hours ?? 12);
    }
}
