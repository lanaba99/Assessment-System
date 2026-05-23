<?php

declare(strict_types=1);

namespace App\Domains\Analytics\Models;

use App\Domains\Shared\Traits\AutoFillsTenantId;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnalyticsCache extends Model
{
    use AutoFillsTenantId;
    use HasFactory;
    use UsesUuid;

    protected $table = 'analytics_cache';

    protected $primaryKey = 'cache_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'cache_key',
        'cache_value',
        'computed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'cache_value' => 'array',
            'computed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
