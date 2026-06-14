<?php

declare(strict_types=1);

namespace App\Domains\Central\Policies;

use App\Domains\Central\Models\CentralAdminUser;
use App\Models\Tenant;

class CentralTenantPolicy
{
    public function viewAny(CentralAdminUser $actor): bool
    {
        return $this->isSuperAdmin($actor);
    }

    public function view(CentralAdminUser $actor, Tenant $tenant): bool
    {
        return $this->isSuperAdmin($actor);
    }

    public function create(CentralAdminUser $actor): bool
    {
        return $this->isSuperAdmin($actor);
    }

    public function update(CentralAdminUser $actor, Tenant $tenant): bool
    {
        return $this->isSuperAdmin($actor);
    }

    private function isSuperAdmin(CentralAdminUser $actor): bool
    {
        return (bool) $actor->is_super_admin
            || in_array('*', (array) $actor->admin_permissions, true);
    }
}
