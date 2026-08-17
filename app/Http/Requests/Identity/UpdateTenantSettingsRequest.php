<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        return app(\App\Domains\Identity\Policies\TenantSettingsPolicy::class)->manage($user);
    }
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_name' => ['sometimes', 'string', 'max:255'],
            'organization_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'primary_contact_email' => ['sometimes', 'email'],
            'primary_contact_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}