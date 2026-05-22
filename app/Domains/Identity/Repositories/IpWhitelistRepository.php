<?php

declare(strict_types=1);

namespace App\Domains\Identity\Repositories;

use App\Domains\Identity\Models\IpWhitelist;
use Illuminate\Support\Collection;

class IpWhitelistRepository
{
    public function __construct(
        private readonly IpWhitelist $model,
    ) {
    }

    public function findById(string $tenantId, string $whitelistId): ?IpWhitelist
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->whereKey($whitelistId)
            ->first();
    }

    /**
     * @return Collection<int, IpWhitelist>
     */
    public function listActiveForTenant(string $tenantId): Collection
    {
        $now = now();

        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(function ($query) use ($now): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->get();
    }

    /**
     * Exact-match (no CIDR/range arithmetic at the SQL layer).
     * Range matching is a Service concern — fetch active rows, evaluate in PHP.
     */
    public function findExactMatch(string $tenantId, string $ipAddress): ?IpWhitelist
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('ip_address', $ipAddress)
            ->first();
    }

    public function create(string $tenantId, string $createdByUserId, array $attributes): IpWhitelist
    {
        $attributes['tenant_id'] = $tenantId;
        $attributes['created_by_user_id'] = $createdByUserId;
        $attributes['created_at'] = $attributes['created_at'] ?? now();

        return $this->model->newQuery()->create($attributes);
    }

    public function deactivate(string $tenantId, string $whitelistId): ?IpWhitelist
    {
        $entry = $this->findById($tenantId, $whitelistId);

        if ($entry === null) {
            return null;
        }

        $entry->fill(['is_active' => false])->save();

        return $entry;
    }
}
