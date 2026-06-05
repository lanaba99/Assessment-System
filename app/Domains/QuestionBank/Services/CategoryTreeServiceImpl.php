<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Services;

use App\Domains\QuestionBank\Contracts\CategoryTreeService;
use App\Domains\QuestionBank\Exceptions\CategoryNotEmptyException;
use App\Domains\QuestionBank\Models\Category;
use App\Domains\QuestionBank\Repositories\CategoryRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class CategoryTreeServiceImpl implements CategoryTreeService
{
    public function __construct(
        private readonly CategoryRepository $categories,
    ) {
    }

    public function getTree(string $tenantId): array
    {
        $nodes = $this->categories->allForTenant($tenantId);

        return $this->buildTree($nodes);
    }

    public function createCategory(
        string $tenantId,
        string $title,
        ?string $parentId = null,
        ?string $description = null,
    ): Category {
        $parent = null;
        $hierarchyLevel = 0;

        if ($parentId !== null) {
            $parent = $this->categories->findById($tenantId, $parentId);

            if ($parent === null) {
                throw new RuntimeException('Parent category not found.');
            }

            $hierarchyLevel = (int) $parent->hierarchy_level + 1;
        }

        return $this->categories->create($tenantId, [
            'parent_category_id' => $parentId,
            'category_name' => $title,
            'category_code' => $this->generateCategoryCode($title),
            'category_description' => $description,
            'display_order' => 0,
            'hierarchy_level' => $hierarchyLevel,
            'is_locked' => false,
            'is_active' => true,
        ]);
    }

    public function moveCategory(string $tenantId, string $categoryId, ?string $parentId): Category
    {
        $category = $this->categories->findById($tenantId, $categoryId);

        if ($category === null) {
            throw new RuntimeException('Category not found.');
        }

        if ($parentId === $categoryId) {
            throw new RuntimeException('A category cannot be its own parent.');
        }

        $hierarchyLevel = 0;

        if ($parentId !== null) {
            $parent = $this->categories->findById($tenantId, $parentId);

            if ($parent === null) {
                throw new RuntimeException('Parent category not found.');
            }

            if ($this->isDescendant($tenantId, $parentId, $categoryId)) {
                throw new RuntimeException('Cannot move a category under one of its descendants.');
            }

            $hierarchyLevel = (int) $parent->hierarchy_level + 1;
        }

        return $this->categories->update($category, [
            'parent_category_id' => $parentId,
            'hierarchy_level' => $hierarchyLevel,
        ]);
    }

    public function deleteCategory(string $tenantId, string $categoryId): void
    {
        $category = $this->categories->findById($tenantId, $categoryId);

        if ($category === null) {
            throw new RuntimeException('Category not found.');
        }

        $hasChildren = $this->categories->hasChildren($tenantId, $categoryId);
        $hasQuestions = $this->categories->hasQuestions($categoryId);

        if ($hasChildren || $hasQuestions) {
            throw new CategoryNotEmptyException($hasChildren, $hasQuestions);
        }

        $this->categories->delete($category);
    }

    /**
     * @param  Collection<int, Category>  $nodes
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(Collection $nodes, ?string $parentId = null): array
    {
        return $nodes
            ->filter(static fn (Category $node): bool => $node->parent_category_id === $parentId)
            ->values()
            ->map(function (Category $node) use ($nodes): array {
                return [
                    'id' => (string) $node->category_id,
                    'title' => (string) $node->category_name,
                    'parent_id' => $node->parent_category_id,
                    'category_code' => (string) $node->category_code,
                    'hierarchy_level' => (int) $node->hierarchy_level,
                    'is_active' => (bool) $node->is_active,
                    'children' => $this->buildTree($nodes, (string) $node->category_id),
                ];
            })
            ->all();
    }

    private function isDescendant(string $tenantId, string $candidateParentId, string $categoryId): bool
    {
        $current = $this->categories->findById($tenantId, $candidateParentId);

        while ($current !== null && $current->parent_category_id !== null) {
            if ((string) $current->parent_category_id === $categoryId) {
                return true;
            }

            $current = $this->categories->findById($tenantId, (string) $current->parent_category_id);
        }

        return false;
    }

    private function generateCategoryCode(string $title): string
    {
        $slug = Str::upper(Str::slug(Str::limit($title, 40, ''), '-'));

        if ($slug === '') {
            $slug = 'CAT';
        }

        return $slug . '-' . Str::upper(Str::substr((string) Str::uuid(), 0, 8));
    }
}
