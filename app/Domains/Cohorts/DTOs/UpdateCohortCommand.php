<?php

declare(strict_types=1);

namespace App\Domains\Cohorts\DTOs;

final readonly class UpdateCohortCommand
{
    public function __construct(
        public ?string $cohortName = null,
        public ?string $cohortCode = null,
        public ?string $cohortType = null,
        public ?string $cohortDescription = null,
        public ?array $cohortAttributes = null,
        public ?bool $isActive = null,
    ) {
    }
}
