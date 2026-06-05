<?php

declare(strict_types=1);

namespace App\Domains\Competency\Services;

use App\Domains\Competency\Contracts\CompetencyTreeService;
use App\Domains\Competency\Exceptions\CompetencyNotEmptyException;
use App\Domains\Competency\Models\Competency;
use App\Domains\Competency\Repositories\CompetencyRepository;
use Illuminate\Support\Collection;
use RuntimeException;

class CompetencyTreeServiceImpl implements CompetencyTreeService
{
    public function __construct(
        private readonly CompetencyRepository $competencies,
    ) {
    }

    public function getTree(string $tenantId): array
    {
        return $this->buildTree($this->competencies->allForTenant($tenantId));
    }

    public function createCompetency(
        string $tenantId,
        string $createdByUserId,
        string $name,
        ?string $parentId = null,
        ?string $description = null,
    ): Competency {
        $hierarchyLevel = 0;

        if ($parentId !== null) {
            $parent = $this->competencies->findById($tenantId, $parentId);

            if ($parent === null) {
                throw new RuntimeException('Parent competency not found.');
            }

            $hierarchyLevel = (int) $parent->hierarchy_level + 1;
        }

        return $this->competencies->create([
            'tenant_id' => $tenantId,
            'created_by_user_id' => $createdByUserId,
            'parent_competency_id' => $parentId,
            'competency_name' => $name,
            'competency_type' => Competency::TYPE_KNOWLEDGE,
            'description' => $description,
            'hierarchy_level' => $hierarchyLevel,
            'is_active' => true,
        ]);
    }

    public function moveCompetency(string $tenantId, string $competencyId, ?string $parentId): Competency
    {
        $competency = $this->competencies->findById($tenantId, $competencyId);

        if ($competency === null) {
            throw new RuntimeException('Competency not found.');
        }

        if ($parentId === $competencyId) {
            throw new RuntimeException('A competency cannot be its own parent.');
        }

        $hierarchyLevel = 0;

        if ($parentId !== null) {
            $parent = $this->competencies->findById($tenantId, $parentId);

            if ($parent === null) {
                throw new RuntimeException('Parent competency not found.');
            }

            if ($this->isDescendant($tenantId, $parentId, $competencyId)) {
                throw new RuntimeException('Cannot move a competency under one of its descendants.');
            }

            $hierarchyLevel = (int) $parent->hierarchy_level + 1;
        }

        return $this->competencies->update($competency, [
            'parent_competency_id' => $parentId,
            'hierarchy_level' => $hierarchyLevel,
        ]);
    }

    public function deleteCompetency(string $tenantId, string $competencyId): void
    {
        $competency = $this->competencies->findById($tenantId, $competencyId);

        if ($competency === null) {
            throw new RuntimeException('Competency not found.');
        }

        $hasChildren = $this->competencies->hasChildren($tenantId, $competencyId);
        $hasQuestions = $this->competencies->hasLinkedQuestions($competencyId);

        if ($hasChildren || $hasQuestions) {
            throw new CompetencyNotEmptyException($hasChildren, $hasQuestions);
        }

        $this->competencies->delete($competency);
    }

    /**
     * @param  Collection<int, Competency>  $nodes
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(Collection $nodes, ?string $parentId = null): array
    {
        return $nodes
            ->filter(static fn (Competency $node): bool => $node->parent_competency_id === $parentId)
            ->values()
            ->map(fn (Competency $node): array => [
                'id' => (string) $node->competency_id,
                'name' => (string) $node->competency_name,
                'parent_id' => $node->parent_competency_id,
                'hierarchy_level' => (int) $node->hierarchy_level,
                'is_active' => (bool) $node->is_active,
                'children' => $this->buildTree($nodes, (string) $node->competency_id),
            ])
            ->all();
    }

    private function isDescendant(string $tenantId, string $candidateParentId, string $competencyId): bool
    {
        $current = $this->competencies->findById($tenantId, $candidateParentId);

        while ($current !== null && $current->parent_competency_id !== null) {
            if ((string) $current->parent_competency_id === $competencyId) {
                return true;
            }

            $current = $this->competencies->findById($tenantId, (string) $current->parent_competency_id);
        }

        return false;
    }
}
