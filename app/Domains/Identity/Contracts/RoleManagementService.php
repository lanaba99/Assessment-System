<?php

declare(strict_types=1);

namespace App\Domains\Identity\Contracts;

/**
 * Write-side RBAC administration. Mutating operations should be wrapped in DB::transaction
 * by implementations and should emit audit events on every change.
 * Collaborates with: RoleRepository, PermissionRepository.
 */
interface RoleManagementService
{
    public function createRole(
        string $tenantId,
        string $roleName,
        ?string $description,
        string $roleCategory,
        bool $isCustom,
    ): string;

    public function deleteRole(string $tenantId, string $roleId): bool;

    public function assignRoleToUser(string $tenantId, string $userId, string $roleId, string $assignedByUserId): void;

    public function removeRoleFromUser(string $tenantId, string $userId, string $roleId, string $removedByUserId): void;

    /**
     * @param  array<int, string>  $permissionIds
     */
    public function syncRolePermissions(string $tenantId, string $roleId, array $permissionIds, string $changedByUserId): void;
}
