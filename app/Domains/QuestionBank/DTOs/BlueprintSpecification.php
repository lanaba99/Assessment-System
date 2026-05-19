<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\DTOs;

final readonly class BlueprintSpecification
{
    public function __construct(
        public string $tenantId,
        public string $examId,
        public int $totalQuestions,
        public array $competencyWeights,
        public array $bloomDistribution,
        public float $targetDifficulty = 0.60,
        public float $minDiscrimination = 0.20,
        public bool $requireCalibrated = true,
    ) {
    }
}
