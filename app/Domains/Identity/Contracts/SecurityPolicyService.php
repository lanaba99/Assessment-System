<?php

declare(strict_types=1);

namespace App\Domains\Identity\Contracts;

use App\Domains\Identity\DTOs\PasswordValidationResult;
use App\Domains\Identity\Models\SecurityPolicy;

/**
 * Policy authoring + enforcement. Read paths should be cached per tenant
 * since most authentication flows consult the policy.
 * Collaborates with: SecurityPolicyRepository.
 */
interface SecurityPolicyService
{
    public function getActivePolicy(string $tenantId): ?SecurityPolicy;

    /**
     * @param  array<string, mixed>  $changes
     */
    public function updatePolicy(string $tenantId, array $changes, string $updatedByUserId): SecurityPolicy;

    public function validatePassword(string $tenantId, string $plaintextPassword): PasswordValidationResult;

    public function isMfaRequired(string $tenantId): bool;

    public function isIpWhitelistingEnabled(string $tenantId): bool;

    public function sessionIdleTimeoutMinutes(string $tenantId): int;

    public function sessionAbsoluteTimeoutHours(string $tenantId): int;
}
