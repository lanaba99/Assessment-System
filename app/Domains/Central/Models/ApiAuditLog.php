<?php

declare(strict_types=1);

namespace App\Domains\Central\Models;

use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiAuditLog extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'api_audit_logs';

    protected $primaryKey = 'log_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'key_id',
        'tenant_id',
        'request_path',
        'request_method',
        'response_status',
        'client_ip',
        'execution_time_ms',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'response_status' => 'integer',
            'execution_time_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(TenantApiKey::class, 'key_id', 'key_id');
    }
}
