<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Repositories;

use App\Domains\QuestionBank\Models\Category;
use Illuminate\Support\Collection;

class CategoryRepository
{
    public function __construct(
        private readonly Category $model,
    ) {
    }

    /**
     * @return Collection<int, Category>
     */
    public function allForTenant(string $tenantId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->orderBy('display_order')
            ->orderBy('category_name')
            ->get();
    }

    public function findById(string $tenantId, string $categoryId): ?Category
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->whereKey($categoryId)
            ->first();
    }

    /**
     * Tenant-safe existence check that relies on the BelongsToTenant global
     * scope (audit Option A) rather than a hand-written tenant_id clause.
     */
    public function exists(string $categoryId): bool
    {
        return $this->model->newQuery()->whereKey($categoryId)->exists();
    }

    public function create(string $tenantId, array $attributes): Category
    {
        $attributes['tenant_id'] = $tenantId;

        return $this->model->newQuery()->forceCreate($attributes);
    }

    public function update(Category $category, array $attributes): Category
    {
        $category->forceFill($attributes)->save();

        return $category;
    }

    public function delete(Category $category): void
    {
        $category->delete();
    }

    public function hasChildren(string $tenantId, string $categoryId): bool
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('parent_category_id', $categoryId)
            ->exists();
    }

    public function hasQuestions(string $categoryId): bool
    {
        return $this->model
            ->newQuery()
            ->whereKey($categoryId)
            ->whereHas('questions')
            ->exists();
    }
}
