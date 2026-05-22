<?php

declare(strict_types=1);

namespace App\Domains\Penalties\Repositories;

use App\Domains\Penalties\Models\PenaltyRule;
use Illuminate\Support\Collection;

class PenaltyRuleRepository
{
    public function __construct(
        private readonly PenaltyRule $model,
    ) {
    }

    /**
     * @return Collection<int, PenaltyRule>
     */
    public function findActiveForTenant(string $tenantId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();
    }
}
