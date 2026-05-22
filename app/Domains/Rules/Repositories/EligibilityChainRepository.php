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
}
