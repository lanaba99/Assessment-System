<?php

declare(strict_types=1);

namespace App\Domains\Competency\Exceptions;

class CompetencyFrameworkNotFoundException extends CompetencyDomainException
{
    public static function withId(string $tenantId, string $frameworkId): self
    {
        return new self("Competency framework {$frameworkId} not found in tenant {$tenantId}.");
    }
}
