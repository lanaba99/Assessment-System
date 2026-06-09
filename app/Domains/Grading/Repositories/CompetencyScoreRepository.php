<?php

declare(strict_types=1);

namespace App\Domains\Grading\Repositories;

use App\Domains\Grading\Models\CompetencyScore;
use Illuminate\Support\Collection;

/**
 * Tenant isolation: explicit where('tenant_id') on every query.
 * CompetencyScore uses AutoFillsTenantId (no global scope), so this repository
 * is the primary isolation layer for competency score data.
 *
 * The unique business key for a competency score row is (session_id, competency_id).
 * upsertForSession implements create-or-update semantics on that key.
 */
class CompetencyScoreRepository
{
    public function __construct(
        private readonly CompetencyScore $model,
    ) {
    }

    /**
     * @return Collection<int, CompetencyScore>
     */
    public function findBySession(string $tenantId, string $sessionId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('session_id', $sessionId)
            ->get();
    }

    public function findBySessionAndCompetency(
        string $tenantId,
        string $sessionId,
        string $competencyId,
    ): ?CompetencyScore {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('session_id', $sessionId)
            ->where('competency_id', $competencyId)
            ->first();
    }

    /**
     * Create or update the score for a (session, competency) pair.
     * Uses forceCreate/forceFill so server-controlled columns persist regardless
     * of the model's $fillable list.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function upsertForSession(
        string $tenantId,
        string $sessionId,
        string $competencyId,
        string $candidateId,
        array $attributes,
    ): CompetencyScore {
        $base = [
            'tenant_id' => $tenantId,
            'session_id' => $sessionId,
            'competency_id' => $competencyId,
            'candidate_user_id' => $candidateId,
            'calculated_at' => now(),
        ];

        $full = array_merge($attributes, $base);

        $existing = $this->findBySessionAndCompetency($tenantId, $sessionId, $competencyId);

        if ($existing !== null) {
            $existing->forceFill($full)->save();

            return $existing;
        }

        return $this->model->newQuery()->forceCreate($full);
    }
}
