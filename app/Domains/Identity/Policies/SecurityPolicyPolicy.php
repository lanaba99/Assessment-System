<?php

declare(strict_types=1);

namespace App\Domains\Identity\Policies;

use App\Domains\Identity\Contracts\AuthorizationService;
use App\Domains\Identity\Models\SecurityPolicy;
use App\Domains\Identity\Models\User;

/**
 * Naming quirk: model is `SecurityPolicy`, policy class must be `SecurityPolicyPolicy`
 * per Laravel's auto-discovery (`{Model}Policy`).
 */
class SecurityPolicyPolicy
{
    public function __construct(
        private readonly AuthorizationService $auth,
    ) {
    }

    public function view(User $actor, SecurityPolicy $policy): bool
    {
        if (! $this->sameTenant($actor, $policy)) {
            return false;
        }

        return $this->auth->userHasPermission(
            (string) $actor->tenant_id,
            (string) $actor->id,
            'security_policies.view',
        );
    }

    public function update(User $actor, SecurityPolicy $policy): bool
    {
        if (! $this->sameTenant($actor, $policy)) {
            return false;
        }

        return $this->auth->userHasPermission(
            (string) $actor->tenant_id,
            (string) $actor->id,
            'security_policies.update',
        );
    }

    private function sameTenant(User $actor, SecurityPolicy $policy): bool
    {
        return (string) $actor->tenant_id === (string) $policy->tenant_id;
    }
}
