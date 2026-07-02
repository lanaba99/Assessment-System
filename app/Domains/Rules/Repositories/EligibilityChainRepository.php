<?php

declare(strict_types=1);

namespace App\Domains\Rules\Repositories;

use App\Domains\Rules\Models\EligibilityChain;
use Illuminate\Support\Collection;

class EligibilityChainRepository
{
    public function __construct(
        private readonly EligibilityChain $model,
    ) {
    }

    /**
     * @return Collection<int, EligibilityChain>
     */
    public function findForExam(string $tenantId, string $examId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('exam_id', $examId)
            ->orderBy('chain_step_number')
            ->get();
    }

    public function findById(string $tenantId, string $chainId): ?EligibilityChain
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('chain_id', $chainId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): EligibilityChain
    {
        return $this->model->newQuery()->forceCreate($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(EligibilityChain $chain, array $attributes): EligibilityChain
    {
        $chain->forceFill($attributes)->save();

        return $chain;
    }

    public function delete(EligibilityChain $chain): void
    {
        $chain->delete();
    }
}