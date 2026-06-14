<?php

declare(strict_types=1);

namespace App\Domains\Penalties\Services;

use App\Domains\Penalties\Models\PenaltySanction;
use App\Domains\Penalties\Repositories\PenaltySanctionRepository;
use Illuminate\Support\Collection;

class PenaltySanctionService
{
    public function __construct(
        private readonly PenaltySanctionRepository $sanctions,
    ) {
    }

    /**
     * @return Collection<int, PenaltySanction>
     */
    public function listForSession(string $tenantId, string $sessionId): Collection
    {
        return $this->sanctions->findForSession($tenantId, $sessionId);
    }

    public function voidSanction(
        string $tenantId,
        string $sanctionId,
        string $voidedByUserId,
        string $reason,
    ): PenaltySanction {
        $sanction = $this->sanctions->findById($tenantId, $sanctionId);

        if ($sanction === null) {
            throw new \RuntimeException("Sanction [{$sanctionId}] not found for tenant.");
        }

        $metadata = is_array($sanction->sanction_metadata) ? $sanction->sanction_metadata : [];
        $metadata['voided_by_user_id'] = $voidedByUserId;
        $metadata['void_reason'] = $reason;
        $metadata['voided_at'] = now()->toIso8601String();

        $sanction->forceFill([
            'sanction_type' => 'voided',
            'sanction_metadata' => $metadata,
        ])->save();

        return $sanction;
    }
}
