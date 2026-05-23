<?php

declare(strict_types=1);

namespace App\Domains\Competency\DTOs;

final readonly class CreateCompetencyData
{
    /**
     * @param  string  $type  One of Competency::TYPE_KNOWLEDGE | TYPE_SKILL | TYPE_ABILITY
     * @param  array<string, mixed>|null  $attributes  Free-form metadata; reserved key `parent_competency_id` is stripped — pass it via $parentCompetencyId instead.
     */
    public function __construct(
        public string $tenantId,
        public string $createdByUserId,
        public string $name,
        public string $type,
        public ?string $code = null,
        public ?string $category = null,
        public ?string $description = null,
        public ?string $parentCompetencyId = null,
        public ?array $attributes = null,
        public bool $isMandatory = false,
        public bool $isActive = true,
        public int $proficiencyLevelCount = 5,
    ) {
    }
}
