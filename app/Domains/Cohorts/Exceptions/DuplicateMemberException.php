<?php

declare(strict_types=1);

namespace App\Domains\Cohorts\Exceptions;

use RuntimeException;

class DuplicateMemberException extends RuntimeException
{
    public static function forUser(string $userId, string $cohortId): self
    {
        return new self("User [{$userId}] is already an active member of cohort [{$cohortId}].");
    }
}
