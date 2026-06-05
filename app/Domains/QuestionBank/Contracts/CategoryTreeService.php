<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Contracts;

use App\Domains\QuestionBank\Models\Category;

interface CategoryTreeService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTree(string $tenantId): array;

    public function createCategory(
        string $tenantId,
        string $title,
        ?string $parentId = null,
        ?string $description = null,
    ): Category;

    public function moveCategory(string $tenantId, string $categoryId, ?string $parentId): Category;

    public function deleteCategory(string $tenantId, string $categoryId): void;
}
