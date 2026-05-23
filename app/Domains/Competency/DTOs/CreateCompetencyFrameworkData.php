<?php

declare(strict_types=1);

namespace App\Domains\Competency\DTOs;

final readonly class CreateCompetencyFrameworkData
{
    /**
     * @param  array<int, array<string, mixed>>|null  $competencyStructure
     * @param  array<string, mixed>|null              $defaultWeights
     */
    public function __construct(
        public string $tenantId,
        public string $createdByUserId,
        public string $name,
        public ?string $description = null,
        public ?array $competencyStructure = null,
        public ?array $defaultWeights = null,
        public bool $isGlobal = false,
    ) {
    }
}
