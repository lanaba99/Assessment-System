<?php

declare(strict_types=1);

namespace App\Domains\Cohorts\Policies;

use App\Domains\Cohorts\Models\Cohort;
use App\Domains\Identity\Contracts\AuthorizationService;
use App\Domains\Identity\Models\User;

class CohortPolicy
{
    public function __construct(
        private readonly AuthorizationService $auth,
    ) {
    }

    public function viewAny(User $actor): bool
    {
        return $this->hasPermission($actor, 'cohorts.view');
    }

    public function view(User $actor, Cohort $cohort): bool
    {
        if (! $this->sameTenant($actor, $cohort)) {
            return false;
        }

        return $this->hasPermission($actor, 'cohorts.view');
    }

    public function create(User $actor): bool
    {
        return $this->hasPermission($actor, 'cohorts.manage');
    }

    public function update(User $actor, Cohort $cohort): bool
    {
        if (! $this->sameTenant($actor, $cohort)) {
            return false;
        }

        return $this->hasPermission($actor, 'cohorts.manage');
    }

    public function delete(User $actor, Cohort $cohort): bool
    {
        if (! $this->sameTenant($actor, $cohort)) {
            return false;
        }

        return $this->hasPermission($actor, 'cohorts.manage');
    }

    public function manageMembers(User $actor, Cohort $cohort): bool
    {
        if (! $this->sameTenant($actor, $cohort)) {
            return false;
        }

        return $this->hasPermission($actor, 'cohorts.members.manage');
    }

    private function hasPermission(User $actor, string $permission): bool
    {
        return $this->auth->userHasPermission(
            (string) $actor->tenant_id,
            (string) $actor->id,
            $permission,
        );
    }

    private function sameTenant(User $actor, Cohort $cohort): bool
    {
        return (string) $actor->tenant_id === (string) $cohort->tenant_id;
    }
}
