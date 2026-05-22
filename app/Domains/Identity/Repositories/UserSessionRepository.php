<?php

declare(strict_types=1);

namespace App\Domains\Identity\Repositories;

use App\Domains\Identity\Models\UserSession;
use Illuminate\Support\Collection;

class UserSessionRepository
{
    public function __construct(
        private readonly UserSession $model,
    ) {
    }

    public function findById(string $tenantId, string $sessionId): ?UserSession
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->whereKey($sessionId)
            ->first();
    }

    /**
     * @return Collection<int, UserSession>
     */
    public function listActiveForUser(string $tenantId, string $userId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereNull('logout_at')
            ->orderByDesc('last_activity_at')
            ->get();
    }

    public function create(string $tenantId, string $userId, array $attributes): UserSession
    {
        $attributes['tenant_id'] = $tenantId;
        $attributes['user_id'] = $userId;
        $attributes['login_at'] = $attributes['login_at'] ?? now();
        $attributes['last_activity_at'] = $attributes['last_activity_at'] ?? now();
        $attributes['session_state'] = $attributes['session_state'] ?? 'active';

        return $this->model->newQuery()->create($attributes);
    }

    public function touchActivity(UserSession $session): UserSession
    {
        $session->fill(['last_activity_at' => now()])->save();

        return $session;
    }

    public function close(string $tenantId, string $sessionId): ?UserSession
    {
        $session = $this->findById($tenantId, $sessionId);

        if ($session === null) {
            return null;
        }

        $session->fill([
            'session_state' => 'closed',
            'logout_at' => now(),
        ])->save();

        return $session;
    }

    public function revokeAllForUser(string $tenantId, string $userId): int
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereNull('logout_at')
            ->update([
                'session_state' => 'revoked',
                'logout_at' => now(),
            ]);
    }
}
