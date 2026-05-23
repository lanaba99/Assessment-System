<?php

declare(strict_types=1);

namespace App\Domains\Competency\Exceptions;

class ProficiencyLevelNotFoundException extends CompetencyDomainException
{
    public static function withId(string $levelId): self
    {
        return new self("Proficiency level {$levelId} not found.");
    }

    public static function forCompetencyLevelNumber(string $competencyId, int $levelNumber): self
    {
        return new self("Proficiency level number {$levelNumber} not found for competency {$competencyId}.");
    }
}
