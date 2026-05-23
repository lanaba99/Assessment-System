<?php

declare(strict_types=1);

namespace App\Domains\Competency\Exceptions;

class DuplicateCompetencyException extends CompetencyDomainException
{
    public static function forName(string $name): self
    {
        return new self("A competency named '{$name}' already exists.");
    }

    public static function forCode(string $code): self
    {
        return new self("A competency with code '{$code}' already exists.");
    }
}
