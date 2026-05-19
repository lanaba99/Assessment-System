<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\DTOs;

final readonly class ItemResolutionRequest
{
    public function __construct(
        public string $tenantId,
        public string $candidateId,
        public int $itemCount,
        public array $competencyWeights,
        public array $bloomDistribution,
        public float $targetDifficulty = 0.60,
        public float $minDiscrimination = 0.20,
        public int $exposureCooldownDays = 90,
        public array $excludedQuestionVersionIds = [],
        public bool $requireCalibrated = true,
        public string $strategy = 'stratified',
    ) {
    }
}
