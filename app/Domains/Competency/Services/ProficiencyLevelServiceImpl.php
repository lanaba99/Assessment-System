<?php

declare(strict_types=1);

namespace App\Domains\Competency\Services;

use App\Domains\Competency\Contracts\CompetencyRepository;
use App\Domains\Competency\Contracts\ProficiencyLevelRepository;
use App\Domains\Competency\Contracts\ProficiencyLevelService;
use App\Domains\Competency\DTOs\CreateProficiencyLevelData;
use App\Domains\Competency\DTOs\UpdateProficiencyLevelData;
use App\Domains\Competency\Exceptions\CompetencyNotFoundException;
use App\Domains\Competency\Exceptions\DuplicateProficiencyLevelException;
use App\Domains\Competency\Exceptions\ProficiencyLevelNotFoundException;
use App\Domains\Competency\Models\Competency;
use App\Domains\Competency\Models\CompetencyLevel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProficiencyLevelServiceImpl implements ProficiencyLevelService
{
    public function __construct(
        private readonly ProficiencyLevelRepository $levels,
        private readonly CompetencyRepository $competencies,
    ) {
    }

    public function create(string $tenantId, CreateProficiencyLevelData $data): CompetencyLevel
    {
        return DB::transaction(function () use ($tenantId, $data): CompetencyLevel {
            $this->requireOwnedCompetency($tenantId, $data->competencyId);

            if ($this->levels->findByLevelNumber($data->competencyId, $data->levelNumber) !== null) {
                throw DuplicateProficiencyLevelException::forCompetencyLevelNumber(
                    $data->competencyId,
                    $data->levelNumber,
                );
            }

            return $this->levels->create([
                'competency_id' => $data->competencyId,
                'level_number' => $data->levelNumber,
                'level_name' => $data->name,
                'level_description' => $data->description,
                'min_score_threshold' => $data->minScoreThreshold,
                'max_score_threshold' => $data->maxScoreThreshold,
                'assessment_criteria' => $data->assessmentCriteria,
                'learning_resources' => $data->learningResources,
            ]);
        });
    }

    public function update(
        string $tenantId,
        string $levelId,
        UpdateProficiencyLevelData $changes,
    ): CompetencyLevel {
        return DB::transaction(function () use ($tenantId, $levelId, $changes): CompetencyLevel {
            $level = $this->requireOwnedLevel($tenantId, $levelId);

            if ($changes->levelNumber !== null && $changes->levelNumber !== (int) $level->level_number) {
                $clash = $this->levels->findByLevelNumber(
                    (string) $level->competency_id,
                    $changes->levelNumber,
                );

                if ($clash !== null && (string) $clash->level_id !== $levelId) {
                    throw DuplicateProficiencyLevelException::forCompetencyLevelNumber(
                        (string) $level->competency_id,
                        $changes->levelNumber,
                    );
                }
            }

            $attributes = $changes->toAttributes();

            return $attributes === []
                ? $level
                : $this->levels->update($level, $attributes);
        });
    }

    public function delete(string $tenantId, string $levelId): void
    {
        DB::transaction(function () use ($tenantId, $levelId): void {
            $level = $this->requireOwnedLevel($tenantId, $levelId);
            $this->levels->delete($level);
        });
    }

    public function get(string $tenantId, string $levelId): CompetencyLevel
    {
        return $this->requireOwnedLevel($tenantId, $levelId);
    }

    public function listForCompetency(string $tenantId, string $competencyId): Collection
    {
        $this->requireOwnedCompetency($tenantId, $competencyId);

        return $this->levels->listForCompetency($competencyId);
    }

    public function replaceScale(string $tenantId, string $competencyId, array $levels): Collection
    {
        return DB::transaction(function () use ($tenantId, $competencyId, $levels): Collection {
            $this->requireOwnedCompetency($tenantId, $competencyId);

            $this->assertNoDuplicateLevelNumbers($competencyId, $levels);

            $this->levels->deleteAllForCompetency($competencyId);

            $created = new Collection();
            foreach ($levels as $payload) {
                $normalized = new CreateProficiencyLevelData(
                    competencyId: $competencyId,
                    levelNumber: $payload->levelNumber,
                    name: $payload->name,
                    description: $payload->description,
                    minScoreThreshold: $payload->minScoreThreshold,
                    maxScoreThreshold: $payload->maxScoreThreshold,
                    assessmentCriteria: $payload->assessmentCriteria,
                    learningResources: $payload->learningResources,
                );

                $created->push($this->levels->create([
                    'competency_id' => $normalized->competencyId,
                    'level_number' => $normalized->levelNumber,
                    'level_name' => $normalized->name,
                    'level_description' => $normalized->description,
                    'min_score_threshold' => $normalized->minScoreThreshold,
                    'max_score_threshold' => $normalized->maxScoreThreshold,
                    'assessment_criteria' => $normalized->assessmentCriteria,
                    'learning_resources' => $normalized->learningResources,
                ]));
            }

            return $created;
        });
    }

    /**
     * @param  array<int, CreateProficiencyLevelData>  $levels
     */
    private function assertNoDuplicateLevelNumbers(string $competencyId, array $levels): void
    {
        $seen = [];
        foreach ($levels as $level) {
            if (isset($seen[$level->levelNumber])) {
                throw DuplicateProficiencyLevelException::forCompetencyLevelNumber(
                    $competencyId,
                    $level->levelNumber,
                );
            }
            $seen[$level->levelNumber] = true;
        }
    }

    private function requireOwnedCompetency(string $tenantId, string $competencyId): Competency
    {
        $competency = $this->competencies->findById($tenantId, $competencyId);

        if ($competency === null) {
            throw CompetencyNotFoundException::withId($tenantId, $competencyId);
        }

        return $competency;
    }

    /**
     * Loads the level and verifies its parent competency belongs to $tenantId
     * — tenancy enforcement bridges through the parent because competency_levels
     * has no tenant_id column.
     */
    private function requireOwnedLevel(string $tenantId, string $levelId): CompetencyLevel
    {
        $level = $this->levels->findById($levelId);

        if ($level === null) {
            throw ProficiencyLevelNotFoundException::withId($levelId);
        }

        $this->requireOwnedCompetency($tenantId, (string) $level->competency_id);

        return $level;
    }
}
