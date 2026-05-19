<?php

declare(strict_types=1);

namespace App\Domains\Identity\Models;

use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityPolicy extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'security_policies';

    protected $primaryKey = 'policy_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'created_by_user_id',
        'mfa_enabled',
        'mfa_method',
        'password_min_length',
        'password_require_uppercase',
        'password_require_lowercase',
        'password_require_numbers',
        'password_require_special_chars',
        'password_expiry_days',
        'password_history_count',
        'session_timeout_minutes',
        'session_absolute_timeout_hours',
        'session_force_reauth_on_privilege_change',
        'ip_whitelisting_enabled',
        'enable_biometric_auth',
        'enforce_tls_1_3_minimum',
        'disable_weak_ciphers',
        'allowed_ip_ranges',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'mfa_enabled' => 'boolean',
            'password_min_length' => 'integer',
            'password_require_uppercase' => 'boolean',
            'password_require_lowercase' => 'boolean',
            'password_require_numbers' => 'boolean',
            'password_require_special_chars' => 'boolean',
            'password_expiry_days' => 'integer',
            'password_history_count' => 'integer',
            'session_timeout_minutes' => 'integer',
            'session_absolute_timeout_hours' => 'integer',
            'session_force_reauth_on_privilege_change' => 'boolean',
            'ip_whitelisting_enabled' => 'boolean',
            'enable_biometric_auth' => 'boolean',
            'enforce_tls_1_3_minimum' => 'boolean',
            'disable_weak_ciphers' => 'boolean',
            'allowed_ip_ranges' => 'array',
            'updated_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }
}
