<?php

declare(strict_types=1);

namespace App\Domains\Cohorts\Repositories;

use App\Domains\Cohorts\Models\Cohort;
use Illuminate\Support\Collection;

/**
 * Tenant isolation is enforced by explicit where('tenant_id') on every query.
 * The Cohort model uses AutoFillsTenantId (no global scope), so belt-and-suspenders
 * filtering here is the primary isolation mechanism.
 *
 * Writes use forceCreate/forceFill so server-controlled columns persist despite
 * not being in $fillable.
 */
class CohortRepository
{
    public function __construct(
        private readonly Cohort $model,
    ) {
    }

    /**
     * @return Collection<int, Cohort>
     */
    public function allForTenant(string $tenantId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->orderBy('hierarchy_level')
            ->orderBy('cohort_name')
            ->get();
    }

    public function findById(string $tenantId, string $cohortId): ?Cohort
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->whereKey($cohortId)
            ->first();
    }

    public function findByCode(string $tenantId, string $cohortCode): ?Cohort
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('cohort_code', $cohortCode)
            ->first();
    }

    public function existsByCode(string $tenantId, string $cohortCode, ?string $excludeId = null): bool
    {
        $query = $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('cohort_code', $cohortCode);

        if ($excludeId !== null) {
            $query->where('cohort_id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Cohort
    {
        return $this->model->newQuery()->forceCreate($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Cohort $cohort, array $attributes): Cohort
    {
        $cohort->forceFill($attributes)->save();

        return $cohort;
    }

    public function delete(Cohort $cohort): void
    {
        $cohort->delete();
    }

    public function hasActiveMembers(string $cohortId): bool
    {
        return $this->model
            ->newQuery()
            ->whereKey($cohortId)
            ->whereHas('memberships', static fn ($q) => $q->where('is_active_member', true))
            ->exists();
    }

    public function hasChildren(string $tenantId, string $cohortId): bool
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('parent_cohort_id', $cohortId)
            ->exists();
    }
}
