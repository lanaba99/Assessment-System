<?php

declare(strict_types=1);

namespace App\Domains\Identity\Models;

use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use UsesUuid;

    protected $table = 'users';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'external_employee_id',
        'email',
        'password_hash',
        'first_name',
        'last_name',
        'user_type',
        'department_id',
        'status',
        'is_active',
        'activated_at',
        'deactivated_at',
        'user_attributes',
        'last_login_at',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'last_login_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'user_attributes' => 'array',
        ];
    }

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'user_roles',
            'user_id',
            'role_id',
            'id',
            'role_id',
        )->withPivot('assigned_at');
    }

    public function subtype(): HasOne
    {
        return $this->hasOne(UserSubtype::class, 'user_id', 'id');
    }

    public function managedSubordinates(): HasMany
    {
        return $this->hasMany(UserSubtype::class, 'examinee_manager_id', 'id');
    }

    public function managedDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'department_manager_id', 'id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class, 'user_id', 'id');
    }

    public function loginAttempts(): HasMany
    {
        return $this->hasMany(LoginAttempt::class, 'email_attempted', 'email');
    }

    public function mfaDevices(): HasMany
    {
        return $this->hasMany(MfaDevice::class, 'user_id', 'id');
    }

    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreference::class, 'user_id', 'id');
    }

    public function whitelistedIps(): HasMany
    {
        return $this->hasMany(IpWhitelist::class, 'created_by_user_id', 'id');
    }

    public function authoredSecurityPolicies(): HasMany
    {
        return $this->hasMany(SecurityPolicy::class, 'created_by_user_id', 'id');
    }
}
