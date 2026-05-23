<?php

declare(strict_types=1);

namespace App\Domains\Competency\Services;

use App\Domains\Competency\Contracts\CompetencyFrameworkRepository;
use App\Domains\Competency\Contracts\CompetencyFrameworkService;
use App\Domains\Competency\Contracts\CompetencyRepository;
use App\Domains\Competency\DTOs\CreateCompetencyFrameworkData;
use App\Domains\Competency\DTOs\UpdateCompetencyFrameworkData;
use App\Domains\Competency\Exceptions\CompetencyFrameworkNotFoundException;
use App\Domains\Competency\Exceptions\CompetencyNotFoundException;
use App\Domains\Competency\Models\CompetencyFramework;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CompetencyFrameworkServiceImpl implements CompetencyFrameworkService
{
    public function __construct(
        private readonly CompetencyFrameworkRepository $frameworks,
        private readonly CompetencyRepository $competencies,
    ) {
    }

    public function create(CreateCompetencyFrameworkData $data): CompetencyFramework
    {
        return DB::transaction(fn (): CompetencyFramework => $this->frameworks->create([
            'tenant_id' => $data->tenantId,
            'created_by_user_id' => $data->createdByUserId,
            'template_name' => $data->name,
            'template_description' => $data->description,
            'competency_structure' => $data->competencyStructure ?? [],
            'default_weights' => $data->defaultWeights ?? [],
            'is_global_template' => $data->isGlobal,
        ]));
    }

    public function update(string $tenantId, string $frameworkId, UpdateCompetencyFrameworkData $changes): CompetencyFramework
    {
        return DB::transaction(function () use ($tenantId, $frameworkId, $changes): CompetencyFramework {
            $framework = $this->requireOwned($tenantId, $frameworkId);
            $attributes = $changes->toAttributes();

            if ($attributes === []) {
                return $framework;
            }

            return $this->frameworks->update($framework, $attributes);
        });
    }

    public function delete(string $tenantId, string $frameworkId): void
    {
        DB::transaction(function () use ($tenantId, $frameworkId): void {
            $framework = $this->requireOwned($tenantId, $frameworkId);
            $this->frameworks->delete($framework);
        });
    }

    public function get(string $tenantId, string $frameworkId): CompetencyFramework
    {
        return $this->requireOwned($tenantId, $frameworkId);
    }

    public function listForTenant(string $tenantId, bool $includeGlobal = true): Collection
    {
        return $this->frameworks->listForTenant($tenantId, $includeGlobal);
    }

    public function attachCompetency(
        string $tenantId,
        string $frameworkId,
        string $competencyId,
        ?float $weight = null,
    ): CompetencyFramework {
        return DB::transaction(function () use ($tenantId, $frameworkId, $competencyId, $weight): CompetencyFramework {
            $framework = $this->requireOwned($tenantId, $frameworkId);

            if ($this->competencies->findById($tenantId, $competencyId) === null) {
                throw CompetencyNotFoundException::withId($tenantId, $competencyId);
            }

            $structure = is_array($framework->competency_structure) ? $framework->competency_structure : [];
            $structure[$competencyId] = [
                'competency_id' => $competencyId,
                'weight' => $weight,
            ];

            return $this->frameworks->update($framework, ['competency_structure' => $structure]);
        });
    }

    public function detachCompetency(string $tenantId, string $frameworkId, string $competencyId): CompetencyFramework
    {
        return DB::transaction(function () use ($tenantId, $frameworkId, $competencyId): CompetencyFramework {
            $framework = $this->requireOwned($tenantId, $frameworkId);
            $structure = is_array($framework->competency_structure) ? $framework->competency_structure : [];

            unset($structure[$competencyId]);

            return $this->frameworks->update($framework, ['competency_structure' => $structure]);
        });
    }

    private function requireOwned(string $tenantId, string $frameworkId): CompetencyFramework
    {
        $framework = $this->frameworks->findById($tenantId, $frameworkId);

        if ($framework === null) {
            throw CompetencyFrameworkNotFoundException::withId($tenantId, $frameworkId);
        }

        return $framework;
    }
}
