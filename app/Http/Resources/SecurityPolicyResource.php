<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Identity\Models\SecurityPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SecurityPolicy
 */
class SecurityPolicyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'policy_id' => (string) $this->policy_id,
            'tenant_id' => (string) $this->tenant_id,
            'mfa_enabled' => (bool) $this->mfa_enabled,
            'mfa_method' => $this->mfa_method,
            'password_min_length' => (int) $this->password_min_length,
            'password_require_uppercase' => (bool) $this->password_require_uppercase,
            'password_require_lowercase' => (bool) $this->password_require_lowercase,
            'password_require_numbers' => (bool) $this->password_require_numbers,
            'password_require_special_chars' => (bool) $this->password_require_special_chars,
            'password_expiry_days' => $this->password_expiry_days,
            'password_history_count' => $this->password_history_count,
            'session_timeout_minutes' => $this->session_timeout_minutes,
            'session_absolute_timeout_hours' => $this->session_absolute_timeout_hours,
            'session_force_reauth_on_privilege_change' => (bool) $this->session_force_reauth_on_privilege_change,
            'ip_whitelisting_enabled' => (bool) $this->ip_whitelisting_enabled,
            'enable_biometric_auth' => (bool) $this->enable_biometric_auth,
            'enforce_tls_1_3_minimum' => (bool) $this->enforce_tls_1_3_minimum,
            'disable_weak_ciphers' => (bool) $this->disable_weak_ciphers,
            'allowed_ip_ranges' => $this->allowed_ip_ranges,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
