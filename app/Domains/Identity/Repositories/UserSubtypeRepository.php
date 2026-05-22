<?php

declare(strict_types=1);

namespace App\Domains\Identity\Repositories;

use App\Domains\Identity\Models\UserSubtype;

/**
 * Note: the `user_subtypes` table has no `tenant_id` column. Tenant scoping is
 * enforced by joining through the parent User and filtering on `users.tenant_id`.
 * This is the one Identity repository where tenant safety relies on a JOIN, not a direct column filter.
 */
class UserSubtypeRepository
{
    public function __construct(
        private readonly UserSubtype $model,
    ) {
    }

    public function findForUser(string $tenantId, string $userId): ?UserSubtype
    {
        return $this->model
            ->newQuery()
            ->whereKey($userId)
            ->whereHas('user', function ($query) use ($tenantId): void {
                $query->where('tenant_id', $tenantId);
            })
            ->first();
    }

    public function createForUser(string $tenantId, string $userId, array $attributes): ?UserSubtype
    {
        if (! $this->userBelongsToTenant($tenantId, $userId)) {
            return null;
        }

        $attributes['user_id'] = $userId;

        return $this->model->newQuery()->create($attributes);
    }

    public function update(string $tenantId, string $userId, array $attributes): ?UserSubtype
    {
        $subtype = $this->findForUser($tenantId, $userId);

        if ($subtype === null) {
            return null;
        }

        $subtype->fill($attributes)->save();

        return $subtype;
    }

    public function delete(string $tenantId, string $userId): bool
    {
        if (! $this->userBelongsToTenant($tenantId, $userId)) {
            return false;
        }

        return $this->model
            ->newQuery()
            ->whereKey($userId)
            ->delete() > 0;
    }

    private function userBelongsToTenant(string $tenantId, string $userId): bool
    {
        return \App\Domains\Identity\Models\User::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($userId)
            ->exists();
    }
}
