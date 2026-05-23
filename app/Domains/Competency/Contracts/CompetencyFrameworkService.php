<?php

declare(strict_types=1);

namespace App\Domains\Competency\Contracts;

use App\Domains\Competency\DTOs\CreateCompetencyFrameworkData;
use App\Domains\Competency\DTOs\UpdateCompetencyFrameworkData;
use App\Domains\Competency\Models\CompetencyFramework;
use Illuminate\Support\Collection;

/**
 * Manages the lifecycle of a CompetencyFramework (the container that groups
 * competencies for a tenant). Membership of competencies within a framework is
 * persisted inside the framework's `competency_structure` JSON column —
 * see attachCompetency / detachCompetency.
 */
interface CompetencyFrameworkService
{
    public function create(CreateCompetencyFrameworkData $data): CompetencyFramework;

    public function update(string $tenantId, string $frameworkId, UpdateCompetencyFrameworkData $changes): CompetencyFramework;

    public function delete(string $tenantId, string $frameworkId): void;

    public function get(string $tenantId, string $frameworkId): CompetencyFramework;

    /**
     * @return Collection<int, CompetencyFramework>
     */
    public function listForTenant(string $tenantId, bool $includeGlobal = true): Collection;

    /**
     * Add a competency to a framework's structure (idempotent).
     * Returns the framework after persistence.
     */
    public function attachCompetency(
        string $tenantId,
        string $frameworkId,
        string $competencyId,
        ?float $weight = null,
    ): CompetencyFramework;

    public function detachCompetency(string $tenantId, string $frameworkId, string $competencyId): CompetencyFramework;
}
