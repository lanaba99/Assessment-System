<?php

declare(strict_types=1);

namespace App\Domains\Competency\Exceptions;

class CompetencyNotFoundException extends CompetencyDomainException
{
    public static function withId(string $tenantId, string $competencyId): self
    {
        return new self("Competency {$competencyId} not found in tenant {$tenantId}.");
    }
}
