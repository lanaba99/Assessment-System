<?php

declare(strict_types=1);

namespace App\Domains\Penalties\DTOs;

final readonly class AppliedSanction
{
    public function __construct(
        public string $sanctionId,
        public string $penaltyRuleId,
        public string $sessionId,
        public string $candidateId,
        public string $sanctionType,
        public float $sanctionAmount,
        public string $reason,
    ) {
    }
}
