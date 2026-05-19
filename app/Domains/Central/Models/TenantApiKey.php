<?php

declare(strict_types=1);

namespace App\Domains\Central\Models;

use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantApiKey extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'tenant_api_keys';

    protected $primaryKey = 'key_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'key_prefix',
        'key_hash',
        'key_description',
        'permissions',
        'is_active',
        'expires_at',
        'last_used_at',
    ];

    protected $hidden = [
        'key_hash',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(ApiAuditLog::class, 'key_id', 'key_id');
    }
}
