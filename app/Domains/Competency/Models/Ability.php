<?php

declare(strict_types=1);

namespace App\Domains\Competency\Models;

use Illuminate\Database\Eloquent\Builder;

class Ability extends Competency
{
    protected static function booted(): void
    {
        static::addGlobalScope('ability', function (Builder $query): void {
            $query->where('competency_type', self::TYPE_ABILITY);
        });

        static::creating(function (Ability $model): void {
            $model->competency_type = self::TYPE_ABILITY;
        });
    }
}
