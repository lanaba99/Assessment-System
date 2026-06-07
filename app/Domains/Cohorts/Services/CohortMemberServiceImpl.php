<?php

declare(strict_types=1);

namespace App\Domains\Cohorts\Services;

use App\Domains\Cohorts\Contracts\CohortMemberService;
use App\Domains\Cohorts\DTOs\AddMemberCommand;
use App\Domains\Cohorts\Exceptions\CohortNotFoundException;
use App\Domains\Cohorts\Exceptions\DuplicateMemberException;
use App\Domains\Cohorts\Models\CohortMember;
use App\Domains\Cohorts\Repositories\CohortMemberRepository;
use App\Domains\Cohorts\Repositories\CohortRepository;
use Illuminate\Support\Collection;

class CohortMemberServiceImpl implements CohortMemberService
{
    public function __construct(
        private readonly CohortRepository $cohortRepository,
        private readonly CohortMemberRepository $memberRepository,
    ) {
    }

    /**
     * @return Collection<int, CohortMember>
     */
    public function listMembers(string $cohortId): Collection
    {
        return $this->memberRepository->membersOfCohort($cohortId);
    }

    /**
     * @throws CohortNotFoundException
     * @throws DuplicateMemberException
     */
    public function addMember(AddMemberCommand $command): CohortMember
    {
        // Verify the cohort belongs to this tenant before touching memberships.
        $cohort = $this->cohortRepository->findById($command->tenantId, $command->cohortId);
        if ($cohort === null) {
            throw CohortNotFoundException::forId($command->cohortId);
        }

        if ($this->memberRepository->isActiveMember($command->cohortId, $command->userId)) {
            throw DuplicateMemberException::forUser($command->userId, $command->cohortId);
        }

        return $this->memberRepository->create([
            'cohort_id' => $command->cohortId,
            'user_id' => $command->userId,
            'tenant_id' => $command->tenantId,
            'membership_role' => $command->membershipRole,
            'added_at' => now(),
            'is_active_member' => true,
        ]);
    }

    /**
     * @throws CohortNotFoundException
     */
    public function removeMember(string $tenantId, string $cohortId, string $userId): void
    {
        // Confirm the cohort is scoped to this tenant before acting on its membership.
        $cohort = $this->cohortRepository->findById($tenantId, $cohortId);
        if ($cohort === null) {
            throw CohortNotFoundException::forId($cohortId);
        }

        $member = $this->memberRepository->findMembership($cohortId, $userId);

        if ($member === null || ! $member->is_active_member) {
            throw new CohortNotFoundException("User [{$userId}] is not an active member of cohort [{$cohortId}].");
        }

        $this->memberRepository->softRemove($member);
    }
}
