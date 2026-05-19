<?php

declare(strict_types=1);

namespace App\Domains\Identity\Models;

use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'user_sessions';

    protected $primaryKey = 'session_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'device_fingerprint',
        'device_id',
        'device_type',
        'browser_name',
        'browser_version',
        'os_name',
        'os_version',
        'screen_width',
        'screen_height',
        'timezone',
        'language_code',
        'ip_address',
        'user_agent',
        'session_state',
        'login_at',
        'last_activity_at',
        'logout_at',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'screen_width' => 'integer',
            'screen_height' => 'integer',
            'login_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'logout_at' => 'datetime',
            'version' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
