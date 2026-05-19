<?php

declare(strict_types=1);

namespace App\Domains\Proctoring\Models;

use Illuminate\Database\Eloquent\Builder;

class AideViolationAlert extends ProctorLog
{
    protected static function booted(): void
    {
        static::addGlobalScope('aide-violation', function (Builder $query): void {
            $query->where('event_category', self::CATEGORY_AIDE_VIOLATION);
        });

        static::creating(function (AideViolationAlert $model): void {
            $model->event_category = self::CATEGORY_AIDE_VIOLATION;
        });
    }
}
