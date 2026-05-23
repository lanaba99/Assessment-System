<?php

declare(strict_types=1);

namespace App\Domains\Competency\DTOs;

/**
 * Partial-update payload. Any property left null is treated as "no change";
 * setting a property to a non-null value rewrites that field.
 */
final readonly class UpdateCompetencyFrameworkData
{
    /**
     * @param  array<int, array<string, mixed>>|null  $competencyStructure
     * @param  array<string, mixed>|null              $defaultWeights
     */
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?array $competencyStructure = null,
        public ?array $defaultWeights = null,
        public ?bool $isGlobal = null,
    ) {
    }

    /**
     * @return array<string, mixed>  Map of column => value for fields that were explicitly set.
     */
    public function toAttributes(): array
    {
        $map = [
            'template_name' => $this->name,
            'template_description' => $this->description,
            'competency_structure' => $this->competencyStructure,
            'default_weights' => $this->defaultWeights,
            'is_global_template' => $this->isGlobal,
        ];

        return array_filter($map, static fn ($value): bool => $value !== null);
    }
}
