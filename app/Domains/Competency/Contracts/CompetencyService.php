<?php

declare(strict_types=1);

namespace App\Domains\Competency\Contracts;

use App\Domains\Competency\DTOs\CreateCompetencyData;
use App\Domains\Competency\DTOs\UpdateCompetencyData;
use App\Domains\Competency\Models\Competency;
use Illuminate\Support\Collection;

/**
 * Manages the Competency entity (Knowledge / Skill / Ability), including its
 * hierarchical relationships. Parent/child references are stored in
 * `competency_attributes.parent_competency_id` (no dedicated FK column).
 */
interface CompetencyService
{
    public function create(CreateCompetencyData $data): Competency;

    public function update(string $tenantId, string $competencyId, UpdateCompetencyData $changes): Competency;

    /**
     * Soft-deactivates a competency. Hard delete is intentionally not exposed:
     * competencies are referenced by exam_blueprints / question_competency_weights
     * with onDelete('restrict').
     */
    public function deactivate(string $tenantId, string $competencyId): Competency;

    public function activate(string $tenantId, string $competencyId): Competency;

    public function get(string $tenantId, string $competencyId): Competency;

    /**
     * @return Collection<int, Competency>
     */
    public function listForTenant(string $tenantId, bool $onlyActive = false): Collection;

    /**
     * @return Collection<int, Competency>  Direct children only.
     */
    public function listChildren(string $tenantId, string $parentCompetencyId): Collection;

    /**
     * @return Collection<int, Competency>  Competencies with no parent.
     */
    public function listRoots(string $tenantId): Collection;

    /**
     * Re-parent a competency. Pass null to detach (move to root).
     * Implementations MUST refuse the change if it would create a cycle
     * or cross tenants, throwing InvalidCompetencyHierarchyException.
     */
    public function moveUnderParent(
        string $tenantId,
        string $competencyId,
        ?string $newParentCompetencyId,
    ): Competency;
}
