<?php

declare(strict_types=1);

namespace App\Domains\Competency\DTOs;

/**
 * Partial-update payload. To clear the parent, pass `clearParent = true`
 * (passing parentCompetencyId = null alone is ambiguous with "no change").
 */
final readonly class UpdateCompetencyData
{
    /**
     * @param  array<string, mixed>|null  $attributes
     */
    public function __construct(
        public ?string $name = null,
        public ?string $code = null,
        public ?string $type = null,
        public ?string $category = null,
        public ?string $description = null,
        public ?string $parentCompetencyId = null,
        public bool $clearParent = false,
        public ?array $attributes = null,
        public ?bool $isMandatory = null,
        public ?bool $isActive = null,
        public ?int $proficiencyLevelCount = null,
    ) {
    }

    /**
     * Returns only the table-level columns (not parent — that lives in JSON,
     * handled by the repository).
     *
     * @return array<string, mixed>
     */
    public function toColumnAttributes(): array
    {
        $map = [
            'competency_name' => $this->name,
            'competency_code' => $this->code,
            'competency_type' => $this->type,
            'competency_category' => $this->category,
            'description' => $this->description,
            'is_mandatory' => $this->isMandatory,
            'is_active' => $this->isActive,
            'proficiency_level_count' => $this->proficiencyLevelCount,
        ];

        return array_filter($map, static fn ($value): bool => $value !== null);
    }
}
