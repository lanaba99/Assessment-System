<?php

declare(strict_types=1);

namespace App\Domains\Cohorts\Services;

use App\Domains\Cohorts\Contracts\CohortManagementService;
use App\Domains\Cohorts\DTOs\CreateCohortCommand;
use App\Domains\Cohorts\DTOs\UpdateCohortCommand;
use App\Domains\Cohorts\Exceptions\CohortNotFoundException;
use App\Domains\Cohorts\Exceptions\CohortNotEmptyException;
use App\Domains\Cohorts\Models\Cohort;
use App\Domains\Cohorts\Repositories\CohortMemberRepository;
use App\Domains\Cohorts\Repositories\CohortRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CohortManagementServiceImpl implements CohortManagementService
{
    public function __construct(
        private readonly CohortRepository $repository,
        private readonly CohortMemberRepository $memberRepository,
    ) {
    }

    /**
     * @return Collection<int, Cohort>
     */
    public function listCohorts(string $tenantId): Collection
    {
        return $this->repository->allForTenant($tenantId);
    }

    /**
     * @throws CohortNotFoundException
     */
    public function getCohort(string $tenantId, string $cohortId): Cohort
    {
        return $this->loadOrFail($tenantId, $cohortId);
    }

    public function createCohort(CreateCohortCommand $command): Cohort
    {
        return DB::transaction(function () use ($command): Cohort {
            $hierarchyLevel = $command->hierarchyLevel;

            if ($command->parentCohortId !== null) {
                $parent = $this->loadOrFail($command->tenantId, $command->parentCohortId);
                $hierarchyLevel = $parent->hierarchy_level + 1;
            }

            return $this->repository->create([
                'tenant_id' => $command->tenantId,
                'created_by_user_id' => $command->createdByUserId,
                'cohort_name' => $command->cohortName,
                'cohort_code' => $command->cohortCode,
                'cohort_type' => $command->cohortType,
                'cohort_description' => $command->cohortDescription,
                'parent_cohort_id' => $command->parentCohortId,
                'hierarchy_level' => $hierarchyLevel,
                'cohort_attributes' => $command->cohortAttributes,
                'is_active' => true,
            ]);
        });
    }

    /**
     * Only non-null command fields are applied, enabling true PATCH semantics.
     *
     * @throws CohortNotFoundException
     */
    public function updateCohort(string $tenantId, string $cohortId, UpdateCohortCommand $command): Cohort
    {
        return DB::transaction(function () use ($tenantId, $cohortId, $command): Cohort {
            $cohort = $this->loadOrFail($tenantId, $cohortId);

            $attributes = array_filter([
                'cohort_name' => $command->cohortName,
                'cohort_code' => $command->cohortCode,
                'cohort_type' => $command->cohortType,
                'cohort_description' => $command->cohortDescription,
                'cohort_attributes' => $command->cohortAttributes,
                'is_active' => $command->isActive,
            ], static fn (mixed $value): bool => $value !== null);

            if ($attributes !== []) {
                $cohort = $this->repository->update($cohort, $attributes);
            }

            return $cohort;
        });
    }

    /**
     * @throws CohortNotFoundException
     * @throws CohortNotEmptyException
     */
    public function deleteCohort(string $tenantId, string $cohortId): void
    {
        $cohort = $this->loadOrFail($tenantId, $cohortId);

        if ($this->repository->hasActiveMembers($cohortId)) {
            throw new CohortNotEmptyException('Cannot delete a cohort that has active members.');
        }

        if ($this->repository->hasChildren($tenantId, $cohortId)) {
            throw new CohortNotEmptyException('Cannot delete a cohort that has child cohorts.');
        }

        $this->repository->delete($cohort);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * @throws CohortNotFoundException
     */
    private function loadOrFail(string $tenantId, string $cohortId): Cohort
    {
        return $this->repository->findById($tenantId, $cohortId)
            ?? throw CohortNotFoundException::forId($cohortId);
    }
}
