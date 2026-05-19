<?php

declare(strict_types=1);

namespace App\Domains\Competency\Models;

use Illuminate\Database\Eloquent\Builder;

class Skill extends Competency
{
    protected static function booted(): void
    {
        static::addGlobalScope('skill', function (Builder $query): void {
            $query->where('competency_type', self::TYPE_SKILL);
        });

        static::creating(function (Skill $model): void {
            $model->competency_type = self::TYPE_SKILL;
        });
    }
}
