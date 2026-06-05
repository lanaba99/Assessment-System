<?php

declare(strict_types=1);

namespace App\Domains\Competency\Contracts;

use App\Domains\Competency\Models\Competency;

interface CompetencyTreeService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTree(string $tenantId): array;

    public function createCompetency(
        string $tenantId,
        string $createdByUserId,
        string $name,
        ?string $parentId = null,
        ?string $description = null,
    ): Competency;

    public function moveCompetency(string $tenantId, string $competencyId, ?string $parentId): Competency;

    public function deleteCompetency(string $tenantId, string $competencyId): void;
}
