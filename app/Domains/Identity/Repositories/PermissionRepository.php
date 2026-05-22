<?php

declare(strict_types=1);

namespace App\Domains\Identity\Repositories;

use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Collection;

class PermissionRepository
{
    public function __construct(
        private readonly Permission $model,
        private readonly Role $roleModel,
        private readonly User $userModel,
    ) {
    }

    public function findById(string $tenantId, string $permissionId): ?Permission
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->whereKey($permissionId)
            ->first();
    }

    public function findByName(string $tenantId, string $permissionName): ?Permission
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('permission_name', $permissionName)
            ->first();
    }

    /**
     * @return Collection<int, Permission>
     */
    public function listForTenant(string $tenantId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->orderBy('resource_type')
            ->orderBy('action_type')
            ->get();
    }

    /**
     * Resolves the full set of permissions for a user (union across all assigned roles, tenant-scoped).
     *
     * @return Collection<int, Permission>
     */
    public function listForUser(string $tenantId, string $userId): Collection
    {
        $user = $this->userModel
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->whereKey($userId)
            ->first();

        if ($user === null) {
            return new Collection();
        }

        $roleIds = $user->roles()
            ->where('roles.tenant_id', $tenantId)
            ->pluck('roles.role_id')
            ->all();

        if ($roleIds === []) {
            return new Collection();
        }

        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->whereHas('roles', function ($query) use ($roleIds): void {
                $query->whereIn('roles.role_id', $roleIds);
            })
            ->get();
    }

    public function syncRolePermissions(string $tenantId, string $roleId, array $permissionIds): void
    {
        $role = $this->roleModel
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->whereKey($roleId)
            ->first();

        if ($role === null) {
            return;
        }

        $validPermissionIds = $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->whereIn('permission_id', $permissionIds)
            ->pluck('permission_id')
            ->all();

        $role->permissions()->sync($validPermissionIds);
    }

    public function create(string $tenantId, array $attributes): Permission
    {
        $attributes['tenant_id'] = $tenantId;
        $attributes['created_at'] = $attributes['created_at'] ?? now();

        return $this->model->newQuery()->create($attributes);
    }
}
