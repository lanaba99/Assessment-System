<?php

declare(strict_types=1);

namespace App\Domains\Identity\Models;

use App\Domains\Shared\Traits\AutoFillsTenantId;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MfaDevice extends Model
{
    use AutoFillsTenantId;
    use HasFactory;
    use UsesUuid;

    protected $table = 'mfa_devices';

    protected $primaryKey = 'mfa_device_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'device_type',
        'device_identifier',
        'device_name',
        'secret_key_hash',
        'is_backup_code',
        'is_verified',
        'backup_codes_count',
        'verified_at',
        'created_at',
        'last_used_at',
    ];

    protected $hidden = [
        'secret_key_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_backup_code' => 'boolean',
            'is_verified' => 'boolean',
            'backup_codes_count' => 'integer',
            'verified_at' => 'datetime',
            'created_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
