<?php

declare(strict_types=1);

namespace App\Domains\Penalties\Policies;

use App\Domains\Identity\Contracts\AuthorizationService;
use App\Domains\Identity\Models\User;
use App\Domains\Penalties\Models\PenaltyRule;
use App\Domains\Penalties\Models\PenaltySanction;

class PenaltyPolicy
{
    public function __construct(
        private readonly AuthorizationService $auth,
    ) {
    }

    public function viewAny(User $actor): bool
    {
        return $this->hasPermission($actor, 'penalties.view')
            || $this->hasPermission($actor, 'penalties.manage');
    }

    public function view(User $actor, PenaltyRule $rule): bool
    {
        return $this->sameTenant($actor, (string) $rule->tenant_id)
            && ($this->hasPermission($actor, 'penalties.view')
                || $this->hasPermission($actor, 'penalties.manage'));
    }

    public function create(User $actor): bool
    {
        return $this->hasPermission($actor, 'penalties.manage');
    }

    public function update(User $actor, PenaltyRule $rule): bool
    {
        return $this->sameTenant($actor, (string) $rule->tenant_id)
            && $this->hasPermission($actor, 'penalties.manage');
    }

    public function delete(User $actor, PenaltyRule $rule): bool
    {
        return $this->update($actor, $rule);
    }

    public function viewSanctions(User $actor): bool
    {
        return $this->hasPermission($actor, 'penalties.view')
            || $this->hasPermission($actor, 'penalties.manage');
    }

    public function voidSanction(User $actor, PenaltySanction $sanction): bool
    {
        return $this->sameTenant($actor, (string) $sanction->tenant_id)
            && $this->hasPermission($actor, 'penalties.manage');
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
