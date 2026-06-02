<?php

declare(strict_types=1);

namespace App\Domains\Identity\Repositories;

use App\Domains\Identity\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class UserRepository
{
    public function __construct(
        private readonly User $model,
    ) {
    }

    public function findById(string $tenantId, string $userId): ?User
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->whereKey($userId)
            ->first();
    }

    public function findByEmail(string $tenantId, string $email): ?User
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('email', $email)
            ->first();
    }

    public function findByExternalEmployeeId(string $tenantId, string $externalId): ?User
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('external_employee_id', $externalId)
            ->first();
    }

    /**
     * @return Collection<int, User>
     */
    public function listActiveByDepartment(string $tenantId, string $departmentId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('department_id', $departmentId)
            ->where('is_active', true)
            ->get();
    }

    public function create(string $tenantId, array $attributes): User
    {
        $attributes['tenant_id'] = $tenantId;

        return $this->model->newQuery()->create($attributes);
    }

    public function update(User $user, array $attributes): User
    {
        $user->fill($attributes)->save();

        return $user;
    }

    public function deactivate(string $tenantId, string $userId): ?User
    {
        $user = $this->findById($tenantId, $userId);

        if ($user === null) {
            return null;
        }

        $user->fill([
            'is_active' => false,
            'status' => 'deactivated',
            'deactivated_at' => now(),
        ])->save();

        return $user;
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
