<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Domains\Identity\Models\SecurityPolicy;
use App\Domains\Identity\Repositories\SecurityPolicyRepository;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSecurityPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $policy = $this->resolvePolicy();

        return $policy !== null && $this->user() !== null && $this->user()->can('update', $policy);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mfa_enabled' => ['sometimes', 'boolean'],
            'mfa_method' => ['sometimes', 'nullable', 'string', 'max:64'],
            'password_min_length' => ['sometimes', 'integer', 'min:8', 'max:128'],
            'password_require_uppercase' => ['sometimes', 'boolean'],
            'password_require_lowercase' => ['sometimes', 'boolean'],
            'password_require_numbers' => ['sometimes', 'boolean'],
            'password_require_special_chars' => ['sometimes', 'boolean'],
            'password_expiry_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:3650'],
            'password_history_count' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:24'],
            'session_timeout_minutes' => ['sometimes', 'nullable', 'integer', 'min:5', 'max:1440'],
            'session_absolute_timeout_hours' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:168'],
            'session_force_reauth_on_privilege_change' => ['sometimes', 'boolean'],
            'ip_whitelisting_enabled' => ['sometimes', 'boolean'],
            'enable_biometric_auth' => ['sometimes', 'boolean'],
            'enforce_tls_1_3_minimum' => ['sometimes', 'boolean'],
            'disable_weak_ciphers' => ['sometimes', 'boolean'],
            'allowed_ip_ranges' => ['sometimes', 'nullable', 'array'],
            'allowed_ip_ranges.*' => ['string', 'max:64'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        return $this->validated();
    }

    private function resolvePolicy(): ?SecurityPolicy
    {
        if (! function_exists('tenant') || tenant() === null) {
            return null;
        }

        return $this->container
            ->make(SecurityPolicyRepository::class)
            ->findActiveForTenant((string) tenant()->getKey());
    }
}
