<?php

declare(strict_types=1);

namespace App\Domains\Penalties\Repositories;

use App\Domains\Penalties\Models\PenaltySanction;

class PenaltySanctionRepository
{
    public function __construct(
        private readonly PenaltySanction $model,
    ) {
    }

    /**
     * Idempotency check — has this rule already sanctioned this session for this exact proctor event?
     */
    public function existsForSessionRuleAndEvent(string $sessionId, string $penaltyRuleId, string $eventId): bool
    {
        return $this->model
            ->newQuery()
            ->where('session_id', $sessionId)
            ->where('penalty_rule_id', $penaltyRuleId)
            ->where('sanction_metadata->source_event_id', $eventId)
            ->exists();
    }

    public function create(array $attributes): PenaltySanction
    {
        return $this->model->newQuery()->create($attributes);
    }
}
