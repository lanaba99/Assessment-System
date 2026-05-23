<?php

declare(strict_types=1);

namespace App\Domains\Competency\Repositories;

use App\Domains\Competency\Contracts\ProficiencyLevelRepository;
use App\Domains\Competency\Models\CompetencyLevel;
use Illuminate\Support\Collection;

class EloquentProficiencyLevelRepository implements ProficiencyLevelRepository
{
    public function __construct(
        private readonly CompetencyLevel $model,
    ) {
    }

    public function findById(string $levelId): ?CompetencyLevel
    {
        return $this->model->newQuery()
            ->whereKey($levelId)
            ->first();
    }

    public function findByLevelNumber(string $competencyId, int $levelNumber): ?CompetencyLevel
    {
        return $this->model->newQuery()
            ->where('competency_id', $competencyId)
            ->where('level_number', $levelNumber)
            ->first();
    }

    public function listForCompetency(string $competencyId): Collection
    {
        return $this->model->newQuery()
            ->where('competency_id', $competencyId)
            ->orderBy('level_number')
            ->get();
    }

    public function create(array $attributes): CompetencyLevel
    {
        $attributes['created_at'] ??= now();

        return $this->model->newQuery()->create($attributes);
    }

    public function update(CompetencyLevel $level, array $attributes): CompetencyLevel
    {
        $level->fill($attributes)->save();

        return $level->refresh();
    }

    public function delete(CompetencyLevel $level): void
    {
        $level->delete();
    }

    public function deleteAllForCompetency(string $competencyId): void
    {
        $this->model->newQuery()
            ->where('competency_id', $competencyId)
            ->delete();
    }
}
