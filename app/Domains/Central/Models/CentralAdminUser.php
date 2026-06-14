<?php

declare(strict_types=1);

namespace App\Domains\Central\Models;

use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class CentralAdminUser extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use UsesUuid;

    protected $table = 'central_admin_users';

    protected $primaryKey = 'admin_user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'email',
        'password_hash',
        'first_name',
        'last_name',
        'admin_permissions',
        'is_super_admin',
        'mfa_enabled',
        'mfa_settings',
        'last_login_at',
        'status',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'admin_permissions' => 'array',
            'is_super_admin' => 'boolean',
            'mfa_enabled' => 'boolean',
            'mfa_settings' => 'array',
            'last_login_at' => 'datetime',
        ];
    }

    public function getAuthPassword(): string
    {
        return (string) $this->password_hash;
    }
}
