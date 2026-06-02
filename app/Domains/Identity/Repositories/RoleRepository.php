<?php

declare(strict_types=1);

namespace App\Domains\Identity\Repositories;

use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class RoleRepository
{
    public function __construct(
        private readonly Role $model,
        private readonly User $userModel,
    ) {
    }

    public function findById(string $tenantId, string $roleId): ?Role
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->whereKey($roleId)
            ->first();
    }

    public function findByName(string $tenantId, string $roleName): ?Role
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('role_name', $roleName)
            ->first();
    }

    /**
     * @return Collection<int, Role>
     */
    public function listForTenant(string $tenantId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->orderBy('role_name')
            ->get();
    }

    /**
     * @return Collection<int, Role>
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

        return $user->roles()->where('roles.tenant_id', $tenantId)->get();
    }

    public function create(string $tenantId, array $attributes): Role
    {
        $attributes['tenant_id'] = $tenantId;

        return $this->model->newQuery()->create($attributes);
    }

    public function attachToUser(string $tenantId, string $userId, string $roleId): void
    {
        $user = $this->userModel
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->whereKey($userId)
            ->first();

        $role = $this->findById($tenantId, $roleId);

        if ($user === null || $role === null) {
            return;
        }

        $user->roles()->syncWithoutDetaching([
            $roleId => ['assigned_at' => now()],
        ]);
    }

    public function detachFromUser(string $tenantId, string $userId, string $roleId): void
    {
        $user = $this->userModel
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->whereKey($userId)
            ->first();

        if ($user === null) {
            return;
        }

        $user->roles()->detach($roleId);
    }

    public function paginateForTenant(string $tenantId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }
}
