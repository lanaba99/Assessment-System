<?php

declare(strict_types=1);

namespace App\Domains\Competency\Contracts;

use App\Domains\Competency\Models\Competency;
use Illuminate\Support\Collection;

interface CompetencyRepository
{
    public function findById(string $tenantId, string $competencyId): ?Competency;

    public function findByName(string $tenantId, string $name): ?Competency;

    public function findByCode(string $tenantId, string $code): ?Competency;

    /**
     * @return Collection<int, Competency>
     */
    public function listForTenant(string $tenantId, bool $onlyActive = false): Collection;

    /**
     * Direct children of the given parent.
     *
     * @return Collection<int, Competency>
     */
    public function findChildren(string $tenantId, string $parentCompetencyId): Collection;

    /**
     * Competencies with no parent — the roots of each hierarchy in the tenant.
     *
     * @return Collection<int, Competency>
     */
    public function findRoots(string $tenantId): Collection;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Competency;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Competency $competency, array $attributes): Competency;

    /**
     * Replaces or clears the parent_competency_id key in competency_attributes JSON
     * (the schema has no dedicated column for parent — see CompetencyServiceProvider docs).
     */
    public function setParent(Competency $competency, ?string $parentCompetencyId): Competency;

    public function delete(Competency $competency): void;
}
