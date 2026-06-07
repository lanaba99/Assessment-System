<?php

declare(strict_types=1);

namespace App\Domains\Cohorts\Contracts;

use App\Domains\Cohorts\DTOs\CreateCohortCommand;
use App\Domains\Cohorts\DTOs\UpdateCohortCommand;
use App\Domains\Cohorts\Exceptions\CohortNotFoundException;
use App\Domains\Cohorts\Exceptions\CohortNotEmptyException;
use App\Domains\Cohorts\Models\Cohort;
use Illuminate\Support\Collection;

interface CohortManagementService
{
    /**
     * @return Collection<int, Cohort>
     */
    public function listCohorts(string $tenantId): Collection;

    /**
     * @throws CohortNotFoundException
     */
    public function getCohort(string $tenantId, string $cohortId): Cohort;

    public function createCohort(CreateCohortCommand $command): Cohort;

    /**
     * @throws CohortNotFoundException
     */
    public function updateCohort(string $tenantId, string $cohortId, UpdateCohortCommand $command): Cohort;

    /**
     * @throws CohortNotFoundException
     * @throws CohortNotEmptyException
     */
    public function deleteCohort(string $tenantId, string $cohortId): void;
}
