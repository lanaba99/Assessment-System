<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Contracts\RoleManagementService;
use App\Domains\Identity\Repositories\PermissionRepository;
use App\Domains\Identity\Repositories\RoleRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RoleManagementServiceImpl implements RoleManagementService
{
    public function __construct(
        private readonly RoleRepository $roles,
        private readonly PermissionRepository $permissions,
    ) {
    }

    public function createRole(
        string $tenantId,
        string $roleName,
        ?string $description,
        string $roleCategory,
        bool $isCustom,
    ): string {
        return DB::transaction(function () use ($tenantId, $roleName, $description, $roleCategory, $isCustom): string {
            $existing = $this->roles->findByName($tenantId, $roleName);

            if ($existing !== null) {
                throw new RuntimeException("Role '{$roleName}' already exists in tenant {$tenantId}.");
            }

            $role = $this->roles->create($tenantId, [
                'role_name' => $roleName,
                'description' => $description,
                'role_category' => $roleCategory,
                'is_custom_role' => $isCustom,
                'is_system_role' => ! $isCustom,
            ]);

            return (string) $role->role_id;
        });
    }

    public function deleteRole(string $tenantId, string $roleId): bool
    {
        return DB::transaction(function () use ($tenantId, $roleId): bool {
            $role = $this->roles->findById($tenantId, $roleId);

            if ($role === null) {
                return false;
            }

            if ((bool) $role->is_system_role) {
                throw new RuntimeException("System role '{$role->role_name}' cannot be deleted.");
            }

            return (bool) $role->delete();
        });
    }

    public function assignRoleToUser(
        string $tenantId,
        string $userId,
        string $roleId,
        string $assignedByUserId,
    ): void {
        $this->roles->attachToUser($tenantId, $userId, $roleId);
    }

    public function removeRoleFromUser(
        string $tenantId,
        string $userId,
        string $roleId,
        string $removedByUserId,
    ): void {
        $this->roles->detachFromUser($tenantId, $userId, $roleId);
    }

    public function syncRolePermissions(
        string $tenantId,
        string $roleId,
        array $permissionIds,
        string $changedByUserId,
    ): void {
        $this->permissions->syncRolePermissions($tenantId, $roleId, $permissionIds);
    }

    public function listRoles(string $tenantId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->roles->paginateForTenant($tenantId, $perPage);
    }
}
