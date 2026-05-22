<?php

declare(strict_types=1);

namespace App\Domains\Rules\DTOs;

final readonly class EligibilityContext
{
    public function __construct(
        public string $tenantId,
        public string $candidateId,
        public string $examId,
    ) {
    }
}
