<?php

declare(strict_types=1);

namespace App\Domains\Competency\Contracts;

use App\Domains\Competency\Models\CompetencyLevel;
use Illuminate\Support\Collection;

interface ProficiencyLevelRepository
{
    public function findById(string $levelId): ?CompetencyLevel;

    public function findByLevelNumber(string $competencyId, int $levelNumber): ?CompetencyLevel;

    /**
     * @return Collection<int, CompetencyLevel>
     */
    public function listForCompetency(string $competencyId): Collection;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): CompetencyLevel;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(CompetencyLevel $level, array $attributes): CompetencyLevel;

    public function delete(CompetencyLevel $level): void;

    public function deleteAllForCompetency(string $competencyId): void;
}
