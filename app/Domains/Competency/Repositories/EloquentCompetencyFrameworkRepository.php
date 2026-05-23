<?php

declare(strict_types=1);

namespace App\Domains\Competency\Repositories;

use App\Domains\Competency\Contracts\CompetencyFrameworkRepository;
use App\Domains\Competency\Models\CompetencyFramework;
use Illuminate\Support\Collection;

class EloquentCompetencyFrameworkRepository implements CompetencyFrameworkRepository
{
    public function __construct(
        private readonly CompetencyFramework $model,
    ) {
    }

    public function findById(string $tenantId, string $frameworkId): ?CompetencyFramework
    {
        return $this->scopedQuery($tenantId)
            ->whereKey($frameworkId)
            ->first();
    }

    public function findByName(string $tenantId, string $name): ?CompetencyFramework
    {
        return $this->scopedQuery($tenantId)
            ->where('template_name', $name)
            ->first();
    }

    public function listForTenant(string $tenantId, bool $includeGlobal = true): Collection
    {
        $query = $this->model->newQuery()
            ->where(function ($q) use ($tenantId, $includeGlobal): void {
                $q->where('tenant_id', $tenantId);

                if ($includeGlobal) {
                    $q->orWhere('is_global_template', true);
                }
            })
            ->orderBy('template_name');

        return $query->get();
    }

    public function create(array $attributes): CompetencyFramework
    {
        $attributes['created_at'] ??= now();

        return $this->model->newQuery()->create($attributes);
    }

    public function update(CompetencyFramework $framework, array $attributes): CompetencyFramework
    {
        $framework->fill($attributes)->save();

        return $framework->refresh();
    }

    public function delete(CompetencyFramework $framework): void
    {
        $framework->delete();
    }

    /**
     * Tenant-or-global scope. Reads against this scope never expose another
     * tenant's private framework but do surface global templates.
     */
    private function scopedQuery(string $tenantId)
    {
        return $this->model->newQuery()
            ->where(function ($q) use ($tenantId): void {
                $q->where('tenant_id', $tenantId)
                    ->orWhere('is_global_template', true);
            });
    }
}
