<?php

declare(strict_types=1);

namespace App\Domains\Rules\Services;

use App\Domains\Rules\Models\EligibilityChain;
use App\Domains\Rules\Repositories\EligibilityChainRepository;
use Illuminate\Support\Collection;

class EligibilityChainManagementService
{
    public function __construct(
        private readonly EligibilityChainRepository $chains,
    ) {
    }

    /**
     * @return Collection<int, EligibilityChain>
     */
    public function listForExam(string $tenantId, string $examId): Collection
    {
        return $this->chains->findForExam($tenantId, $examId);
    }

    public function find(string $tenantId, string $chainId): ?EligibilityChain
    {
        return $this->chains->findById($tenantId, $chainId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(string $tenantId, string $createdByUserId, array $data): EligibilityChain
    {
        return $this->chains->create(array_merge($data, [
            'tenant_id' => $tenantId,
            'created_by_user_id' => $createdByUserId,
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(EligibilityChain $chain, array $data): EligibilityChain
    {
        return $this->chains->update($chain, $data);
    }

    public function delete(EligibilityChain $chain): void
    {
        $this->chains->delete($chain);
    }
}