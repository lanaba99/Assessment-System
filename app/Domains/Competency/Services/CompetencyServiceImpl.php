<?php

declare(strict_types=1);

namespace App\Domains\Competency\Services;

use App\Domains\Competency\Contracts\CompetencyRepository;
use App\Domains\Competency\Contracts\CompetencyService;
use App\Domains\Competency\DTOs\CreateCompetencyData;
use App\Domains\Competency\DTOs\UpdateCompetencyData;
use App\Domains\Competency\Exceptions\CompetencyNotFoundException;
use App\Domains\Competency\Exceptions\DuplicateCompetencyException;
use App\Domains\Competency\Exceptions\InvalidCompetencyHierarchyException;
use App\Domains\Competency\Models\Competency;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CompetencyServiceImpl implements CompetencyService
{
    public function __construct(
        private readonly CompetencyRepository $competencies,
    ) {
    }

    public function create(CreateCompetencyData $data): Competency
    {
        return DB::transaction(function () use ($data): Competency {
            if ($this->competencies->findByName($data->tenantId, $data->name) !== null) {
                throw DuplicateCompetencyException::forName($data->name);
            }

            if ($data->code !== null && $this->competencies->findByCode($data->tenantId, $data->code) !== null) {
                throw DuplicateCompetencyException::forCode($data->code);
            }

            $attributes = $data->attributes ?? [];
            unset($attributes['parent_competency_id']);

            if ($data->parentCompetencyId !== null) {
                $parent = $this->competencies->findById($data->tenantId, $data->parentCompetencyId);

                if ($parent === null) {
                    throw InvalidCompetencyHierarchyException::crossTenant('(new)', $data->parentCompetencyId);
                }

                $attributes['parent_competency_id'] = $data->parentCompetencyId;
            }

            return $this->competencies->create([
                'tenant_id' => $data->tenantId,
                'created_by_user_id' => $data->createdByUserId,
                'competency_name' => $data->name,
                'competency_code' => $data->code,
                'competency_type' => $data->type,
                'competency_category' => $data->category,
                'description' => $data->description,
                'competency_attributes' => $attributes,
                'is_mandatory' => $data->isMandatory,
                'is_active' => $data->isActive,
                'proficiency_level_count' => $data->proficiencyLevelCount,
            ]);
        });
    }

    public function update(string $tenantId, string $competencyId, UpdateCompetencyData $changes): Competency
    {
        return DB::transaction(function () use ($tenantId, $competencyId, $changes): Competency {
            $competency = $this->requireOwned($tenantId, $competencyId);

            if ($changes->name !== null && $changes->name !== (string) $competency->competency_name) {
                $existing = $this->competencies->findByName($tenantId, $changes->name);

                if ($existing !== null && (string) $existing->competency_id !== $competencyId) {
                    throw DuplicateCompetencyException::forName($changes->name);
                }
            }

            if ($changes->code !== null && $changes->code !== (string) $competency->competency_code) {
                $existing = $this->competencies->findByCode($tenantId, $changes->code);

                if ($existing !== null && (string) $existing->competency_id !== $competencyId) {
                    throw DuplicateCompetencyException::forCode($changes->code);
                }
            }

            if ($changes->attributes !== null) {
                $merged = array_merge(
                    is_array($competency->competency_attributes) ? $competency->competency_attributes : [],
                    $changes->attributes,
                );
                unset($merged['parent_competency_id']); // parent moves only via moveUnderParent
                $competency->competency_attributes = $merged;
            }

            $columns = $changes->toColumnAttributes();

            if ($columns !== []) {
                $competency = $this->competencies->update($competency, $columns);
            } elseif ($competency->isDirty()) {
                $competency->save();
                $competency->refresh();
            }

            if ($changes->parentCompetencyId !== null || $changes->clearParent) {
                $competency = $this->moveUnderParent(
                    $tenantId,
                    $competencyId,
                    $changes->clearParent ? null : $changes->parentCompetencyId,
                );
            }

            return $competency;
        });
    }

    public function deactivate(string $tenantId, string $competencyId): Competency
    {
        return DB::transaction(function () use ($tenantId, $competencyId): Competency {
            $competency = $this->requireOwned($tenantId, $competencyId);

            return $this->competencies->update($competency, ['is_active' => false]);
        });
    }

    public function activate(string $tenantId, string $competencyId): Competency
    {
        return DB::transaction(function () use ($tenantId, $competencyId): Competency {
            $competency = $this->requireOwned($tenantId, $competencyId);

            return $this->competencies->update($competency, ['is_active' => true]);
        });
    }

    public function get(string $tenantId, string $competencyId): Competency
    {
        return $this->requireOwned($tenantId, $competencyId);
    }

    public function listForTenant(string $tenantId, bool $onlyActive = false): Collection
    {
        return $this->competencies->listForTenant($tenantId, $onlyActive);
    }

    public function listChildren(string $tenantId, string $parentCompetencyId): Collection
    {
        $this->requireOwned($tenantId, $parentCompetencyId);

        return $this->competencies->findChildren($tenantId, $parentCompetencyId);
    }

    public function listRoots(string $tenantId): Collection
    {
        return $this->competencies->findRoots($tenantId);
    }

    public function moveUnderParent(
        string $tenantId,
        string $competencyId,
        ?string $newParentCompetencyId,
    ): Competency {
        return DB::transaction(function () use ($tenantId, $competencyId, $newParentCompetencyId): Competency {
            $competency = $this->requireOwned($tenantId, $competencyId);

            if ($newParentCompetencyId === null) {
                return $this->competencies->setParent($competency, null);
            }

            if ($newParentCompetencyId === $competencyId) {
                throw InvalidCompetencyHierarchyException::selfParent($competencyId);
            }

            $parent = $this->competencies->findById($tenantId, $newParentCompetencyId);

            if ($parent === null) {
                throw InvalidCompetencyHierarchyException::crossTenant($competencyId, $newParentCompetencyId);
            }

            $this->assertNoCycle($tenantId, $competencyId, $newParentCompetencyId);

            return $this->competencies->setParent($competency, $newParentCompetencyId);
        });
    }

    /**
     * Walk the chain of ancestors starting at $startAncestorId. If we ever land
     * on $competencyId, attaching it under that ancestor would close a cycle.
     */
    private function assertNoCycle(string $tenantId, string $competencyId, string $startAncestorId): void
    {
        $cursor = $startAncestorId;
        $seen = [];

        while ($cursor !== null) {
            if ($cursor === $competencyId) {
                throw InvalidCompetencyHierarchyException::cycleDetected($competencyId, $startAncestorId);
            }

            if (isset($seen[$cursor])) {
                // pre-existing cycle in the graph — bail so we don't loop forever
                return;
            }

            $seen[$cursor] = true;
            $ancestor = $this->competencies->findById($tenantId, $cursor);
            $cursor = $ancestor?->parent_competency_id;
        }
    }

    private function requireOwned(string $tenantId, string $competencyId): Competency
    {
        $competency = $this->competencies->findById($tenantId, $competencyId);

        if ($competency === null) {
            throw CompetencyNotFoundException::withId($tenantId, $competencyId);
        }

        return $competency;
    }
}
