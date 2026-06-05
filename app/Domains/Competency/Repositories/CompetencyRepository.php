<?php

declare(strict_types=1);

namespace App\Domains\Competency\Repositories;

use App\Domains\Competency\Models\Competency;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant isolation is enforced by the BelongsToTenant global scope; the
 * explicit where('tenant_id') calls below mirror the Category repository as
 * belt-and-suspenders. Writes use forceCreate/forceFill so server-controlled
 * columns (tenant_id, created_by_user_id) persist despite not being $fillable.
 */
class CompetencyRepository
{
    public function __construct(
        private readonly Competency $model,
    ) {
    }

    /**
     * @return Collection<int, Competency>
     */
    public function allForTenant(string $tenantId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->orderBy('hierarchy_level')
            ->orderBy('competency_name')
            ->get();
    }

    public function findById(string $tenantId, string $competencyId): ?Competency
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->whereKey($competencyId)
            ->first();
    }

    public function exists(string $competencyId): bool
    {
        return $this->model->newQuery()->whereKey($competencyId)->exists();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Competency
    {
        return $this->model->newQuery()->forceCreate($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Competency $competency, array $attributes): Competency
    {
        $competency->forceFill($attributes)->save();

        return $competency;
    }

    public function delete(Competency $competency): void
    {
        $competency->delete();
    }

    public function hasChildren(string $tenantId, string $competencyId): bool
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('parent_competency_id', $competencyId)
            ->exists();
    }

    /**
     * Whether any question is weighted to this competency. Queries the pivot
     * directly and is guarded so it no-ops when the pivot table is absent
     * (e.g. the focused test schema that builds only the competency tables).
     */
    public function hasLinkedQuestions(string $competencyId): bool
    {
        if (! Schema::hasTable('question_competency_weights')) {
            return false;
        }

        return DB::table('question_competency_weights')
            ->where('competency_id', $competencyId)
            ->exists();
    }
}
