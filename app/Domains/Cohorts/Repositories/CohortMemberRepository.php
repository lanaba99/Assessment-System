<?php

declare(strict_types=1);

namespace App\Domains\Cohorts\Repositories;

use App\Domains\Cohorts\Models\CohortMember;
use Illuminate\Support\Collection;

class CohortMemberRepository
{
    public function __construct(
        private readonly CohortMember $model,
    ) {
    }

    /**
     * Returns the membership record regardless of active status (used for
     * duplicate checks and re-activation scenarios).
     */
    public function findMembership(string $cohortId, string $userId): ?CohortMember
    {
        return $this->model
            ->newQuery()
            ->where('cohort_id', $cohortId)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * @return Collection<int, CohortMember>
     */
    public function membersOfCohort(string $cohortId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('cohort_id', $cohortId)
            ->where('is_active_member', true)
            ->with('user')
            ->get();
    }

    /**
     * @return Collection<int, CohortMember>
     */
    public function cohortsOfUser(string $tenantId, string $userId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('is_active_member', true)
            ->with('cohort')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): CohortMember
    {
        return $this->model->newQuery()->forceCreate($attributes);
    }

    /**
     * Soft-removes the membership by setting is_active_member = false and
     * recording the removal timestamp, preserving the audit trail.
     */
    public function softRemove(CohortMember $member): void
    {
        $member->forceFill([
            'is_active_member' => false,
            'removed_at' => now(),
        ])->save();
    }

    public function isActiveMember(string $cohortId, string $userId): bool
    {
        return $this->model
            ->newQuery()
            ->where('cohort_id', $cohortId)
            ->where('user_id', $userId)
            ->where('is_active_member', true)
            ->exists();
    }
}
