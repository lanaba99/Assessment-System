<?php

declare(strict_types=1);

namespace App\Domains\Identity\Contracts;

/**
 * Tenant-perimeter enforcement: IP whitelist evaluation (including CIDR / range checks)
 * and session-policy enforcement (idle/absolute timeouts, force re-auth on privilege change).
 * Collaborates with: IpWhitelistRepository, UserSessionRepository, SecurityPolicyService.
 */
interface TenantAccessService
{
    public function isIpAllowed(string $tenantId, string $ipAddress): bool;

    public function whitelistIp(
        string $tenantId,
        string $createdByUserId,
        string $ipAddress,
        ?string $ipRangeEnd,
        ?string $description,
        ?\DateTimeImmutable $expiresAt,
    ): string;

    public function revokeWhitelistedIp(string $tenantId, string $whitelistId, string $revokedByUserId): bool;

    public function isSessionStillValid(string $tenantId, string $sessionId): bool;

    public function forceReauthForUser(string $tenantId, string $userId, string $reason): int;
}
