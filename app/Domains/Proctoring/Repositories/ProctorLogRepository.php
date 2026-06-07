<?php

declare(strict_types=1);

namespace App\Domains\Proctoring\Repositories;

use App\Domains\Proctoring\Models\ProctorLog;
use Illuminate\Support\Collection;

/**
 * Tenant isolation is enforced by explicit where('tenant_id') on every query.
 * ProctorLog uses AutoFillsTenantId (no global scope), so this repository is
 * the primary isolation layer for proctoring data.
 *
 * Writes use forceCreate so server-controlled columns (tenant_id, UUIDs from
 * UsesUuid) persist regardless of the model's $fillable list.
 */
class ProctorLogRepository
{
    public function __construct(
        private readonly ProctorLog $model,
    ) {
    }

    public function findById(string $tenantId, string $eventId): ?ProctorLog
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->whereKey($eventId)
            ->first();
    }

    /**
     * @return Collection<int, ProctorLog>
     */
    public function listForSession(string $tenantId, string $sessionId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('session_id', $sessionId)
            ->orderBy('event_timestamp', 'desc')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ProctorLog
    {
        return $this->model->newQuery()->forceCreate($attributes);
    }
}
