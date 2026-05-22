<?php

declare(strict_types=1);

namespace App\Domains\Identity\Policies;

use App\Domains\Identity\Contracts\AuthorizationService;
use App\Domains\Identity\Models\User;

class UserPolicy
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
            'users.viewAny',
        );
    }

    public function view(User $actor, User $target): bool
    {
        if (! $this->sameTenant($actor, $target)) {
            return false;
        }

        if ((string) $actor->id === (string) $target->id) {
            return true;
        }

        return $this->auth->userHasPermission(
            (string) $actor->tenant_id,
            (string) $actor->id,
            'users.view',
        );
    }

    public function create(User $actor): bool
    {
        return $this->auth->userHasPermission(
            (string) $actor->tenant_id,
            (string) $actor->id,
            'users.create',
        );
    }

    public function update(User $actor, User $target): bool
    {
        if (! $this->sameTenant($actor, $target)) {
            return false;
        }

        return $this->auth->userHasPermission(
            (string) $actor->tenant_id,
            (string) $actor->id,
            'users.update',
        );
    }

    public function deactivate(User $actor, User $target): bool
    {
        if (! $this->sameTenant($actor, $target)) {
            return false;
        }

        if ((string) $actor->id === (string) $target->id) {
            return false; // never self-deactivate via the API
        }

        return $this->auth->userHasPermission(
            (string) $actor->tenant_id,
            (string) $actor->id,
            'users.deactivate',
        );
    }

    public function resetPassword(User $actor, User $target): bool
    {
        if (! $this->sameTenant($actor, $target)) {
            return false;
        }

        return $this->auth->userHasPermission(
            (string) $actor->tenant_id,
            (string) $actor->id,
            'users.resetPassword',
        );
    }

    private function sameTenant(User $actor, User $target): bool
    {
        return (string) $actor->tenant_id === (string) $target->tenant_id;
    }
}
