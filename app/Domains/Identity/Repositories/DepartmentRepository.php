<?php

declare(strict_types=1);

namespace App\Domains\Identity\Repositories;

use App\Domains\Identity\Models\Department;
use Illuminate\Support\Collection;

class DepartmentRepository
{
    public function __construct(
        private readonly Department $model,
    ) {
    }

    public function findById(string $tenantId, string $departmentId): ?Department
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->whereKey($departmentId)
            ->first();
    }

    public function findByCode(string $tenantId, string $departmentCode): ?Department
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('department_code', $departmentCode)
            ->first();
    }

    /**
     * @return Collection<int, Department>
     */
    public function listRoots(string $tenantId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->whereNull('parent_department_id')
            ->orderBy('department_name')
            ->get();
    }

    /**
     * @return Collection<int, Department>
     */
    public function listChildren(string $tenantId, string $parentDepartmentId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('parent_department_id', $parentDepartmentId)
            ->orderBy('department_name')
            ->get();
    }

    public function create(string $tenantId, array $attributes): Department
    {
        $attributes['tenant_id'] = $tenantId;

        return $this->model->newQuery()->create($attributes);
    }

    public function update(Department $department, array $attributes): Department
    {
        $department->fill($attributes)->save();

        return $department;
    }
}
