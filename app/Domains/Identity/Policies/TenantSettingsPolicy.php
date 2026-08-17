<?php

declare(strict_types=1);

namespace App\Domains\Identity\Policies;

use App\Domains\Identity\Contracts\AuthorizationService;
use App\Domains\Identity\Models\User;

class TenantSettingsPolicy
{
    public function __construct(
        private readonly AuthorizationService $auth,
    ) {
    }

    /**
     * No per-instance model to check ownership against — there is no
     * {tenantId} route param, this always operates on the current tenant
     * only, so a single-argument ability (matching RolePolicy::create()'s
     * pattern) is the correct shape here.
     */
    public function manage(User $actor): bool
    {
        return $this->auth->userHasPermission(
            (string) $actor->tenant_id,
            (string) $actor->id,
            'tenant.manage',
        );
    }
}