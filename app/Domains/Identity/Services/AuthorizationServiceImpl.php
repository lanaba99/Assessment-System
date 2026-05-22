<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Contracts\AuthorizationService;
use App\Domains\Identity\Repositories\PermissionRepository;
use App\Domains\Identity\Repositories\RoleRepository;

/**
 * Read-only permission/role resolver. Stateless.
 *
 * Caching hook: implementations that need to scale should wrap these methods
 * with a per-(tenant, user) cache (key e.g. "identity:perms:{tenantId}:{userId}")
 * and invalidate from RoleManagementService on every role assignment / sync.
 * No caching is wired here to keep the contract surface minimal — callers can
 * either use Laravel's Cache::remember() in middleware or extend this class.
 */
class AuthorizationServiceImpl implements AuthorizationService
{
    public function __construct(
        private readonly PermissionRepository $permissions,
        private readonly RoleRepository $roles,
    ) {
    }

    public function userHasPermission(string $tenantId, string $userId, string $permissionName): bool
    {
        return in_array(
            $permissionName,
            $this->listPermissionNamesForUser($tenantId, $userId),
            true,
        );
    }

    public function userHasRole(string $tenantId, string $userId, string $roleName): bool
    {
        return in_array(
            $roleName,
            $this->listRoleNamesForUser($tenantId, $userId),
            true,
        );
    }

    public function userHasAnyRole(string $tenantId, string $userId, array $roleNames): bool
    {
        if ($roleNames === []) {
            return false;
        }

        $owned = $this->listRoleNamesForUser($tenantId, $userId);

        return array_intersect(array_map('strval', $roleNames), $owned) !== [];
    }

    /**
     * @return array<int, string>
     */
    public function listPermissionNamesForUser(string $tenantId, string $userId): array
    {
        return $this->permissions
            ->listForUser($tenantId, $userId)
            ->pluck('permission_name')
            ->map(static fn ($n): string => (string) $n)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function listRoleNamesForUser(string $tenantId, string $userId): array
    {
        return $this->roles
            ->listForUser($tenantId, $userId)
            ->pluck('role_name')
            ->map(static fn ($n): string => (string) $n)
            ->unique()
            ->values()
            ->all();
    }
}
