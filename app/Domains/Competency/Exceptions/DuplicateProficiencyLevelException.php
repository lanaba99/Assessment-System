<?php

declare(strict_types=1);

namespace App\Domains\Competency\Exceptions;

class DuplicateProficiencyLevelException extends CompetencyDomainException
{
    public static function forCompetencyLevelNumber(string $competencyId, int $levelNumber): self
    {
        return new self(
            "Proficiency level number {$levelNumber} already exists for competency {$competencyId}.",
        );
    }
}
