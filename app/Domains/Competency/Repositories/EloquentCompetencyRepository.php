<?php

declare(strict_types=1);

namespace App\Domains\Competency\Repositories;

use App\Domains\Competency\Contracts\CompetencyRepository;
use App\Domains\Competency\Models\Competency;
use Illuminate\Support\Collection;

class EloquentCompetencyRepository implements CompetencyRepository
{
    public function __construct(
        private readonly Competency $model,
    ) {
    }

    public function findById(string $tenantId, string $competencyId): ?Competency
    {
        return $this->model->newQuery()
            ->where('tenant_id', $tenantId)
            ->whereKey($competencyId)
            ->first();
    }

    public function findByName(string $tenantId, string $name): ?Competency
    {
        return $this->model->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('competency_name', $name)
            ->first();
    }

    public function findByCode(string $tenantId, string $code): ?Competency
    {
        return $this->model->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('competency_code', $code)
            ->first();
    }

    public function listForTenant(string $tenantId, bool $onlyActive = false): Collection
    {
        $query = $this->model->newQuery()
            ->where('tenant_id', $tenantId);

        if ($onlyActive) {
            $query->where('is_active', true);
        }

        return $query->orderBy('competency_name')->get();
    }

    public function findChildren(string $tenantId, string $parentCompetencyId): Collection
    {
        return $this->model->newQuery()
            ->where('tenant_id', $tenantId)
            ->whereJsonContains('competency_attributes->parent_competency_id', $parentCompetencyId)
            ->orderBy('competency_name')
            ->get();
    }

    public function findRoots(string $tenantId): Collection
    {
        return $this->model->newQuery()
            ->where('tenant_id', $tenantId)
            ->where(function ($q): void {
                $q->whereNull('competency_attributes')
                    ->orWhereNull('competency_attributes->parent_competency_id');
            })
            ->orderBy('competency_name')
            ->get();
    }

    public function create(array $attributes): Competency
    {
        return $this->model->newQuery()->create($attributes);
    }

    public function update(Competency $competency, array $attributes): Competency
    {
        $competency->fill($attributes)->save();

        return $competency->refresh();
    }

    public function setParent(Competency $competency, ?string $parentCompetencyId): Competency
    {
        $attrs = is_array($competency->competency_attributes)
            ? $competency->competency_attributes
            : [];

        if ($parentCompetencyId === null) {
            unset($attrs['parent_competency_id']);
        } else {
            $attrs['parent_competency_id'] = $parentCompetencyId;
        }

        $competency->competency_attributes = $attrs;
        $competency->save();

        return $competency->refresh();
    }

    public function delete(Competency $competency): void
    {
        $competency->delete();
    }
}
