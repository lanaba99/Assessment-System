<?php

declare(strict_types=1);

namespace App\Domains\Competency\Exceptions;

class InvalidCompetencyHierarchyException extends CompetencyDomainException
{
    public static function selfParent(string $competencyId): self
    {
        return new self("Competency {$competencyId} cannot be its own parent.");
    }

    public static function cycleDetected(string $competencyId, string $parentCompetencyId): self
    {
        return new self(
            "Reparenting competency {$competencyId} under {$parentCompetencyId} would create a cycle.",
        );
    }

    public static function crossTenant(string $competencyId, string $parentCompetencyId): self
    {
        return new self(
            "Parent competency {$parentCompetencyId} does not belong to the same tenant as {$competencyId}.",
        );
    }
}
