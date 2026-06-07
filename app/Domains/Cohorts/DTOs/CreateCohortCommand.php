<?php

declare(strict_types=1);

namespace App\Domains\Cohorts\DTOs;

final readonly class CreateCohortCommand
{
    public function __construct(
        public string $tenantId,
        public string $createdByUserId,
        public string $cohortName,
        public string $cohortCode,
        public string $cohortType,
        public ?string $cohortDescription = null,
        public ?string $parentCohortId = null,
        public int $hierarchyLevel = 0,
        public ?array $cohortAttributes = null,
    ) {
    }
}
