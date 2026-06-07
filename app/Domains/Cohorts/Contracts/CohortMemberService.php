<?php

declare(strict_types=1);

namespace App\Domains\Cohorts\Contracts;

use App\Domains\Cohorts\DTOs\AddMemberCommand;
use App\Domains\Cohorts\Exceptions\CohortNotFoundException;
use App\Domains\Cohorts\Exceptions\DuplicateMemberException;
use App\Domains\Cohorts\Models\CohortMember;
use Illuminate\Support\Collection;

interface CohortMemberService
{
    /**
     * @return Collection<int, CohortMember>
     */
    public function listMembers(string $cohortId): Collection;

    /**
     * @throws CohortNotFoundException
     * @throws DuplicateMemberException
     */
    public function addMember(AddMemberCommand $command): CohortMember;

    /**
     * @throws CohortNotFoundException
     */
    public function removeMember(string $tenantId, string $cohortId, string $userId): void;
}
