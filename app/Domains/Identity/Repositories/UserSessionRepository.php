<?php

declare(strict_types=1);

namespace App\Domains\Identity\Repositories;

use App\Domains\Identity\Models\UserSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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

    public function findForUser(string $tenantId, string $userId, string $sessionId): ?UserSession
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
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
        // device_fingerprint is NOT NULL + UNIQUE; callers that have a real
        // fingerprint (e.g. a JS client posting a hash) should override this.
        $attributes['device_fingerprint'] = $attributes['device_fingerprint'] ?? (string) Str::uuid();

        return $this->model->newQuery()->create($attributes);
    }

    public function touchActivity(UserSession $session): UserSession
    {
        $session->fill(['last_activity_at' => now()])->save();

        return $session;
    }

    /**
     * Close a session. When $userId is provided the update is additionally
     * scoped by user_id, blocking cross-user logout-by-session-id attacks.
     */
    public function close(string $tenantId, string $sessionId, ?string $userId = null): ?UserSession
    {
        $query = $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->whereKey($sessionId);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $session = $query->first();

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

    public function revokeForUser(string $tenantId, string $userId, string $sessionId): bool
    {
        $updated = $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereKey($sessionId)
            ->whereNull('logout_at')
            ->update([
                'session_state' => 'revoked',
                'logout_at' => now(),
            ]);

        return $updated > 0;
    }
}
