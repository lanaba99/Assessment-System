<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\DTOs;

final readonly class CoverageReport
{
    public function __construct(
        public array $competencyCoverage,
        public array $bloomCoverage,
        public array $gaps,
        public bool $isFeasible,
    ) {
    }
}
