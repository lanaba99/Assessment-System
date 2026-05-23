<?php

declare(strict_types=1);

namespace App\Domains\Competency\DTOs;

final readonly class CreateProficiencyLevelData
{
    /**
     * @param  array<string, mixed>|null  $assessmentCriteria
     * @param  array<string, mixed>|null  $learningResources
     */
    public function __construct(
        public string $competencyId,
        public int $levelNumber,
        public string $name,
        public ?string $description = null,
        public float $minScoreThreshold = 0.0,
        public float $maxScoreThreshold = 100.0,
        public ?array $assessmentCriteria = null,
        public ?array $learningResources = null,
    ) {
    }
}
