<?php

declare(strict_types=1);

namespace App\Domains\Penalties\Repositories;

use App\Domains\Penalties\Models\PenaltySanction;
use Illuminate\Support\Collection;

/**
 * Tenant isolation: every query carries an explicit tenant_id filter.
 * PenaltySanction uses AutoFillsTenantId (no global scope), so this repository
 * is the primary isolation layer.
 *
 * Writes use forceCreate so tenant_id and other server-controlled columns are
 * always persisted regardless of the model's $fillable list.
 */
class PenaltySanctionRepository
{
    public function __construct(
        private readonly PenaltySanction $model,
    ) {
    }

    /**
     * Return all sanctions for a session within the given tenant.
     * Read-only — used by the Grading domain to compute penalty deductions without
     * touching any Penalties domain write paths.
     *
     * @return Collection<int, PenaltySanction>
     */
    public function findForSession(string $tenantId, string $sessionId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('session_id', $sessionId)
            ->get();
    }

    /**
     * Idempotency guard — has this rule already sanctioned this session for
     * this exact proctor event within the same tenant?
     *
     * The tenant_id filter is belt-and-suspenders protection: a rule from
     * tenant A cannot satisfy the idempotency check for tenant B's session even
     * if session UUIDs were ever to collide.
     */
    public function existsForSessionRuleAndEvent(
        string $tenantId,
        string $sessionId,
        string $penaltyRuleId,
        string $eventId,
    ): bool {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('session_id', $sessionId)
            ->where('penalty_rule_id', $penaltyRuleId)
            ->where('sanction_metadata->source_event_id', $eventId)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): PenaltySanction
    {
        return $this->model->newQuery()->forceCreate($attributes);
    }

    public function findById(string $tenantId, string $sanctionId): ?PenaltySanction
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('sanction_id', $sanctionId)
            ->first();
    }
}
