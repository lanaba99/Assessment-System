<?php

declare(strict_types=1);

namespace App\Domains\Analytics\Policies;

use App\Domains\Analytics\Models\AnalyticsCache;
use App\Domains\Identity\Contracts\AuthorizationService;
use App\Domains\Identity\Models\User;

class AnalyticsPolicy
{
    public function __construct(
        private readonly AuthorizationService $auth,
    ) {
    }

    public function viewDashboard(User $actor): bool
    {
        return $this->hasPermission($actor, 'analytics.view');
    }

    public function view(User $actor, AnalyticsCache $cache): bool
    {
        return (string) $actor->tenant_id === (string) $cache->tenant_id
            && $this->hasPermission($actor, 'analytics.view');
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
