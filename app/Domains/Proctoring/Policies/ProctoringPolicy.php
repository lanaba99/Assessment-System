<?php

declare(strict_types=1);

namespace App\Domains\Proctoring\Policies;

use App\Domains\Identity\Contracts\AuthorizationService;
use App\Domains\Identity\Models\User;

class ProctoringPolicy
{
    public function __construct(
        private readonly AuthorizationService $auth,
    ) {
    }

    /**
     * who can ingest proctoring events for a session?
     * given to the proctoring tool or candidate's browser agent, this is the only endpoint that can ingest events.
     * this is a "system" permission, not given to any human user.
     */
    
    public function viewForSession(User $actor): bool
    {
        return $this->hasPermission($actor, 'proctoring.view');
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