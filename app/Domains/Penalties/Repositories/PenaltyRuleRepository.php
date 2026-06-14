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

    /**
     * @return Collection<int, PenaltyRule>
     */
    public function findAllForTenant(string $tenantId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->orderBy('penalty_name')
            ->get();
    }

    public function findById(string $tenantId, string $ruleId): ?PenaltyRule
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('penalty_rule_id', $ruleId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): PenaltyRule
    {
        return $this->model->newQuery()->forceCreate($attributes);
    }

    public function update(PenaltyRule $rule, array $attributes): PenaltyRule
    {
        $rule->forceFill($attributes)->save();

        return $rule;
    }

    public function delete(PenaltyRule $rule): void
    {
        $rule->delete();
    }
}
