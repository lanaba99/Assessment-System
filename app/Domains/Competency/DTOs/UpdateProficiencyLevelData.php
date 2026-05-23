<?php

declare(strict_types=1);

namespace App\Domains\Competency\DTOs;

final readonly class UpdateProficiencyLevelData
{
    /**
     * @param  array<string, mixed>|null  $assessmentCriteria
     * @param  array<string, mixed>|null  $learningResources
     */
    public function __construct(
        public ?int $levelNumber = null,
        public ?string $name = null,
        public ?string $description = null,
        public ?float $minScoreThreshold = null,
        public ?float $maxScoreThreshold = null,
        public ?array $assessmentCriteria = null,
        public ?array $learningResources = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        $map = [
            'level_number' => $this->levelNumber,
            'level_name' => $this->name,
            'level_description' => $this->description,
            'min_score_threshold' => $this->minScoreThreshold,
            'max_score_threshold' => $this->maxScoreThreshold,
            'assessment_criteria' => $this->assessmentCriteria,
            'learning_resources' => $this->learningResources,
        ];

        return array_filter($map, static fn ($value): bool => $value !== null);
    }
}
