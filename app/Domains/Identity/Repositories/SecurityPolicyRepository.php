<?php

declare(strict_types=1);

namespace App\Domains\Identity\Repositories;

use App\Domains\Identity\Models\SecurityPolicy;

class SecurityPolicyRepository
{
    public function __construct(
        private readonly SecurityPolicy $model,
    ) {
    }

    /**
     * Returns the most recently updated policy row for the tenant.
     * Tenants typically have a single active policy; multiple rows preserve audit history.
     */
    public function findActiveForTenant(string $tenantId): ?SecurityPolicy
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('updated_at')
            ->first();
    }

    public function findById(string $tenantId, string $policyId): ?SecurityPolicy
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->whereKey($policyId)
            ->first();
    }

    public function create(string $tenantId, array $attributes): SecurityPolicy
    {
        $attributes['tenant_id'] = $tenantId;
        $attributes['updated_at'] = $attributes['updated_at'] ?? now();

        return $this->model->newQuery()->create($attributes);
    }

    public function update(SecurityPolicy $policy, array $attributes): SecurityPolicy
    {
        $attributes['updated_at'] = now();
        $policy->fill($attributes)->save();

        return $policy;
    }
}
