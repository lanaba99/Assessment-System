<?php

declare(strict_types=1);

namespace App\Domains\Rules\DTOs;

final readonly class EligibilityResult
{
    /**
     * @param  array<int, array{step: int, condition_type: string, passed: bool, reason: ?string, was_overridden: bool}>  $stepOutcomes
     */
    public function __construct(
        public string $candidateId,
        public string $examId,
        public bool $isEligible,
        public array $stepOutcomes,
        public ?string $rejectionReason = null,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function failedSteps(): array
    {
        return array_values(array_filter(
            $this->stepOutcomes,
            static fn (array $s): bool => $s['passed'] === false,
        ));
    }
}
