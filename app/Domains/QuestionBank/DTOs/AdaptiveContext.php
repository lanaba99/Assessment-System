<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\DTOs;

final readonly class AdaptiveContext
{
    public function __construct(
        public string $sessionId,
        public string $candidateId,
        public string $tenantId,
        public float $abilityEstimate,
        public float $standardError,
        public array $administeredVersionIds,
        public array $targetCompetencyWeights,
        public int $maxItems,
        public float $stoppingStandardError = 0.30,
    ) {
    }
}
