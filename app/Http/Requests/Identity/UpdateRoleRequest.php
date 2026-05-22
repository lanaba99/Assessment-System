<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Repositories\RoleRepository;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $role = $this->resolveRoleForAuthorization();
        if ($role === null) {
            return false;
        }

        // Tenant isolation: deny across-tenant updates outright (policy will double-check).
        if ((string) $role->tenant_id !== (string) $user->tenant_id) {
            return false;
        }

        return $user->can('update', $role);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'role_name' => ['sometimes', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:1024'],
            'role_category' => ['sometimes', 'string', 'max:64'],
            'role_metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        return array_intersect_key(
            $this->validated(),
            array_flip(['role_name', 'description', 'role_category', 'role_metadata']),
        );
    }

    private function resolveRoleForAuthorization(): ?Role
    {
        $roleId = (string) $this->route('roleId');
        $tenantId = (string) $this->user()?->tenant_id;

        if ($roleId === '' || $tenantId === '') {
            return null;
        }

        return app(RoleRepository::class)->findById($tenantId, $roleId);
    }
}
