<?php

declare(strict_types=1);

namespace App\Domains\Identity\Contracts;

/**
 * Read-only permission resolution. Implementations should be cacheable.
 * Collaborates with: PermissionRepository, RoleRepository, UserRepository.
 */
interface AuthorizationService
{
    public function userHasPermission(string $tenantId, string $userId, string $permissionName): bool;

    public function userHasRole(string $tenantId, string $userId, string $roleName): bool;

    public function userHasAnyRole(string $tenantId, string $userId, array $roleNames): bool;

    /**
     * @return array<int, string>  permission names
     */
    public function listPermissionNamesForUser(string $tenantId, string $userId): array;

    /**
     * @return array<int, string>  role names
     */
    public function listRoleNamesForUser(string $tenantId, string $userId): array;
}
