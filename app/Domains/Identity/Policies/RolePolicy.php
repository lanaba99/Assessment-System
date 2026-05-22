<?php

declare(strict_types=1);

namespace App\Domains\Identity\Policies;

use App\Domains\Identity\Contracts\AuthorizationService;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;

class RolePolicy
{
    public function __construct(
        private readonly AuthorizationService $auth,
    ) {
    }

    public function viewAny(User $actor): bool
    {
        return $this->auth->userHasPermission(
            (string) $actor->tenant_id,
            (string) $actor->id,
            'roles.viewAny',
        );
    }

    public function view(User $actor, Role $role): bool
    {
        if (! $this->sameTenant($actor, $role)) {
            return false;
        }

        return $this->auth->userHasPermission(
            (string) $actor->tenant_id,
            (string) $actor->id,
            'roles.view',
        );
    }

    public function create(User $actor): bool
    {
        return $this->auth->userHasPermission(
            (string) $actor->tenant_id,
            (string) $actor->id,
            'roles.create',
        );
    }

    public function update(User $actor, Role $role): bool
    {
        if (! $this->sameTenant($actor, $role)) {
            return false;
        }

        if ((bool) $role->is_system_role) {
            // System roles are immutable through the API.
            return false;
        }

        return $this->auth->userHasPermission(
            (string) $actor->tenant_id,
            (string) $actor->id,
            'roles.update',
        );
    }

    public function delete(User $actor, Role $role): bool
    {
        if (! $this->sameTenant($actor, $role)) {
            return false;
        }

        if ((bool) $role->is_system_role) {
            return false;
        }

        return $this->auth->userHasPermission(
            (string) $actor->tenant_id,
            (string) $actor->id,
            'roles.delete',
        );
    }

    public function assignToUser(User $actor, Role $role): bool
    {
        if (! $this->sameTenant($actor, $role)) {
            return false;
        }

        return $this->auth->userHasPermission(
            (string) $actor->tenant_id,
            (string) $actor->id,
            'roles.assign',
        );
    }

    private function sameTenant(User $actor, Role $role): bool
    {
        return (string) $actor->tenant_id === (string) $role->tenant_id;
    }
}
