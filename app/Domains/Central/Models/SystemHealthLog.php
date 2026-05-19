<?php

declare(strict_types=1);

namespace App\Domains\Central\Models;

use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemHealthLog extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'system_health_logs';

    protected $primaryKey = 'health_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'check_component',
        'check_status',
        'response_time_ms',
        'health_message',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'response_time_ms' => 'integer',
            'checked_at' => 'datetime',
        ];
    }
}
