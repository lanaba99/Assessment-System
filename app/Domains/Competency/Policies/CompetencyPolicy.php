<?php

declare(strict_types=1);

namespace App\Domains\Competency\Policies;

use App\Domains\Competency\Models\Competency;
use App\Domains\Identity\Contracts\AuthorizationService;
use App\Domains\Identity\Models\User;

class CompetencyPolicy
{
    public function __construct(
        private readonly AuthorizationService $auth,
    ) {
    }

    public function viewAny(User $actor): bool
    {
        return $this->hasPermission($actor, 'competencies.manage');
    }

    public function create(User $actor): bool
    {
        return $this->hasPermission($actor, 'competencies.manage');
    }

    public function update(User $actor, Competency $competency): bool
    {
        if (! $this->sameTenant($actor, $competency)) {
            return false;
        }

        return $this->hasPermission($actor, 'competencies.manage');
    }

    public function delete(User $actor, Competency $competency): bool
    {
        if (! $this->sameTenant($actor, $competency)) {
            return false;
        }

        return $this->hasPermission($actor, 'competencies.manage');
    }

    private function hasPermission(User $actor, string $permission): bool
    {
        return $this->auth->userHasPermission(
            (string) $actor->tenant_id,
            (string) $actor->id,
            $permission,
        );
    }

    private function sameTenant(User $actor, Competency $competency): bool
    {
        return (string) $actor->tenant_id === (string) $competency->tenant_id;
    }
}
