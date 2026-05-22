<?php

declare(strict_types=1);

namespace App\Domains\Grading\DTOs;

final readonly class GradingRequest
{
    public function __construct(
        public string $tenantId,
        public string $sessionId,
        public string $sessionItemId,
        public string $candidateId,
        public string $questionId,
        public string $questionVersionId,
        public string $questionType,
        public ?array $correctAnswerKey,
        public string $responseType,
        public ?array $responseData,
        public ?string $responseText,
        public ?array $selectedOptions,
        public float $maxScore = 1.0,
    ) {
    }
}
