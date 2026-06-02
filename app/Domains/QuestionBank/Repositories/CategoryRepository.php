<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Repositories;

use App\Domains\QuestionBank\Models\QuestionBank;
use Illuminate\Support\Collection;

class CategoryRepository
{
    public function __construct(
        private readonly QuestionBank $model,
    ) {
    }

    /**
     * @return Collection<int, QuestionBank>
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

    public function findById(string $tenantId, string $categoryId): ?QuestionBank
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->whereKey($categoryId)
            ->first();
    }

    public function create(string $tenantId, array $attributes): QuestionBank
    {
        $attributes['tenant_id'] = $tenantId;

        return $this->model->newQuery()->create($attributes);
    }

    public function update(QuestionBank $category, array $attributes): QuestionBank
    {
        $category->fill($attributes);
        $category->save();

        return $category;
    }

    public function delete(QuestionBank $category): void
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
