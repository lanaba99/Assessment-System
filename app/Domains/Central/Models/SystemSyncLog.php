<?php

declare(strict_types=1);

namespace App\Domains\Central\Models;

use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSyncLog extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'sync_logs';

    protected $primaryKey = 'sync_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'entity_type',
        'external_system_name',
        'operation_type',
        'records_processed',
        'records_failed',
        'sync_status',
        'error_details',
    ];

    protected function casts(): array
    {
        return [
            'records_processed' => 'integer',
            'records_failed' => 'integer',
            'error_details' => 'array',
        ];
    }
}
