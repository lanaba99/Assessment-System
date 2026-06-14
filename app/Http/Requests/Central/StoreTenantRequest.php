<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Tenant::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_name' => ['required', 'string', 'max:255'],
            'organization_type' => ['nullable', 'string', 'max:100'],
            'primary_contact_email' => ['required', 'email'],
            'primary_contact_phone' => ['nullable', 'string', 'max:50'],
            'domain' => ['required', 'string', 'max:255', 'alpha_dash'],
            'deployment_config' => ['nullable', 'array'],
            'feature_flags' => ['nullable', 'array'],
            'max_concurrent_users' => ['nullable', 'integer', 'min:1'],
            'max_storage_quota_mb' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
