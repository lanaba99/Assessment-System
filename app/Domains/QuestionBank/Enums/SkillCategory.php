<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Enums;

enum SkillCategory: string
{
    case Knowledge = 'knowledge';
    case Skill = 'skill';
    case Ability = 'ability';

    public function label(): string
    {
        return match ($this) {
            self::Knowledge => 'Knowledge',
            self::Skill => 'Skill',
            self::Ability => 'Ability',
        };
    }
}
