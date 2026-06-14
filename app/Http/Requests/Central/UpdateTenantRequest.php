<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Domains\Central\Models\CentralAdminUser;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof CentralAdminUser) {
            return false;
        }

        $tenant = Tenant::query()->find($this->route('tenantId'));

        if ($tenant === null) {
            abort(404, 'Tenant not found.');
        }

        return $user->can('update', $tenant);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_name' => ['sometimes', 'string', 'max:255'],
            'organization_type' => ['sometimes', 'string', 'max:100'],
            'primary_contact_email' => ['sometimes', 'email'],
            'primary_contact_phone' => ['nullable', 'string', 'max:50'],
            'status' => ['sometimes', 'string', 'in:active,suspended,inactive'],
            'feature_flags' => ['nullable', 'array'],
            'max_concurrent_users' => ['nullable', 'integer', 'min:1'],
            'max_storage_quota_mb' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
