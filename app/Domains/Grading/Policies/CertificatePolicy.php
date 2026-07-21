<?php

declare(strict_types=1);

namespace App\Domains\Grading\Policies;

use App\Domains\Grading\Models\Certificate;
use App\Domains\Identity\Contracts\AuthorizationService;
use App\Domains\Identity\Models\User;

class CertificatePolicy
{
    public function __construct(
        private readonly AuthorizationService $auth,
    ) {
    }

    /**
     * Anyone can list certificates — results are scoped to "own" for
     * candidates and "all in tenant" for staff with grading.view, inside
     * the controller itself.
     */
    public function listAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, Certificate $certificate): bool
    {
        if ((string) $actor->tenant_id !== (string) $certificate->tenant_id) {
            return false;
        }

        return (string) $actor->id === (string) $certificate->candidate_user_id
            || $this->hasPermission($actor, 'grading.view');
    }

    public function manage(User $actor, Certificate $certificate): bool
    {
        if ((string) $actor->tenant_id !== (string) $certificate->tenant_id) {
            return false;
        }

        return $this->hasPermission($actor, 'grading.publish');
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