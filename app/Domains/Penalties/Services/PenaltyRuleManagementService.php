<?php

declare(strict_types=1);

namespace App\Domains\Penalties\Services;

use App\Domains\Penalties\Models\PenaltyRule;
use App\Domains\Penalties\Repositories\PenaltyRuleRepository;
use Illuminate\Support\Collection;

class PenaltyRuleManagementService
{
    public function __construct(
        private readonly PenaltyRuleRepository $rules,
    ) {
    }

    /**
     * @return Collection<int, PenaltyRule>
     */
    public function listForTenant(string $tenantId): Collection
    {
        return $this->rules->findAllForTenant($tenantId);
    }

    public function find(string $tenantId, string $ruleId): ?PenaltyRule
    {
        return $this->rules->findById($tenantId, $ruleId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(string $tenantId, string $createdByUserId, array $data): PenaltyRule
    {
        return $this->rules->create(array_merge($data, [
            'tenant_id' => $tenantId,
            'created_by_user_id' => $createdByUserId,
            'is_active' => $data['is_active'] ?? true,
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PenaltyRule $rule, array $data): PenaltyRule
    {
        return $this->rules->update($rule, $data);
    }

    public function delete(PenaltyRule $rule): void
    {
        $this->rules->delete($rule);
    }

    public function setActive(PenaltyRule $rule, bool $active): PenaltyRule
    {
        return $this->rules->update($rule, ['is_active' => $active]);
    }
}
