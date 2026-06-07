<?php

declare(strict_types=1);

namespace App\Domains\Cohorts\Exceptions;

use RuntimeException;

class CohortNotFoundException extends RuntimeException
{
    public static function forId(string $cohortId): self
    {
        return new self("Cohort [{$cohortId}] not found.");
    }
}
