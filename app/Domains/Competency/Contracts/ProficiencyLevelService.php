<?php

declare(strict_types=1);

namespace App\Domains\Competency\Contracts;

use App\Domains\Competency\DTOs\CreateProficiencyLevelData;
use App\Domains\Competency\DTOs\UpdateProficiencyLevelData;
use App\Domains\Competency\Models\CompetencyLevel;
use Illuminate\Support\Collection;

/**
 * Manages the measurement scale (proficiency levels) attached to a Competency.
 * Tenant scope is enforced through the parent competency, so each method
 * accepts $tenantId for the ownership check.
 */
interface ProficiencyLevelService
{
    public function create(string $tenantId, CreateProficiencyLevelData $data): CompetencyLevel;

    public function update(
        string $tenantId,
        string $levelId,
        UpdateProficiencyLevelData $changes,
    ): CompetencyLevel;

    public function delete(string $tenantId, string $levelId): void;

    public function get(string $tenantId, string $levelId): CompetencyLevel;

    /**
     * @return Collection<int, CompetencyLevel>  Ordered by level_number ascending.
     */
    public function listForCompetency(string $tenantId, string $competencyId): Collection;

    /**
     * Replace the entire proficiency scale for a competency atomically:
     * existing levels are deleted and the given DTOs are inserted in their place.
     *
     * @param  array<int, CreateProficiencyLevelData>  $levels
     * @return Collection<int, CompetencyLevel>
     */
    public function replaceScale(
        string $tenantId,
        string $competencyId,
        array $levels,
    ): Collection;
}
