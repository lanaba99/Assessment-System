<?php

declare(strict_types=1);

namespace App\Domains\Rules\Policies;

use App\Domains\Identity\Contracts\AuthorizationService;
use App\Domains\Identity\Models\User;
use App\Domains\Rules\Models\EligibilityChain;

class EligibilityPolicy
{
    public function __construct(
        private readonly AuthorizationService $auth,
    ) {
    }

    public function viewAny(User $actor): bool
    {
        return $this->hasPermission($actor, 'eligibility.view')
            || $this->hasPermission($actor, 'eligibility.manage');
    }

    public function view(User $actor, EligibilityChain $chain): bool
    {
        return $this->sameTenant($actor, (string) $chain->tenant_id)
            && ($this->hasPermission($actor, 'eligibility.view')
                || $this->hasPermission($actor, 'eligibility.manage'));
    }

    public function create(User $actor): bool
    {
        return $this->hasPermission($actor, 'eligibility.manage');
    }

    public function update(User $actor, EligibilityChain $chain): bool
    {
        return $this->sameTenant($actor, (string) $chain->tenant_id)
            && $this->hasPermission($actor, 'eligibility.manage');
    }

    public function delete(User $actor, EligibilityChain $chain): bool
    {
        return $this->update($actor, $chain);
    }

    private function sameTenant(User $actor, string $tenantId): bool
    {
        return (string) $actor->tenant_id === $tenantId;
    }

    private function hasPermission(User $actor, string $permission): bool
    {
        return $this->auth->userHasPermission(
            (string) $actor->tenant_id,
            (string) $actor->id,
            $permission,
        );
    }
}