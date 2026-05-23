<?php

declare(strict_types=1);

namespace App\Domains\Competency\Contracts;

use App\Domains\Competency\Models\CompetencyFramework;
use Illuminate\Support\Collection;

interface CompetencyFrameworkRepository
{
    public function findById(string $tenantId, string $frameworkId): ?CompetencyFramework;

    public function findByName(string $tenantId, string $name): ?CompetencyFramework;

    /**
     * @return Collection<int, CompetencyFramework>
     */
    public function listForTenant(string $tenantId, bool $includeGlobal = true): Collection;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): CompetencyFramework;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(CompetencyFramework $framework, array $attributes): CompetencyFramework;

    public function delete(CompetencyFramework $framework): void;
}
