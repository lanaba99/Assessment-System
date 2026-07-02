<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin-scoped user update. Authorization is enforced at the controller
 * level via `$this->authorize('update', $target)` (UserPolicy@update),
 * NOT here — mirrors UpdateEligibilityChainRequest's pattern, because the
 * target user must be loaded and tenant-scoped before the Policy can run.
 */
class UpdateUserByAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
        // ownership/permission check happens in the controller via $this->authorize('update', $target)
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'external_employee_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'user_type' => ['sometimes', 'string', 'max:64'],
            'department_id' => ['sometimes', 'nullable', 'uuid'],
            'user_attributes' => ['sometimes', 'nullable', 'array'],
            'status' => ['sometimes', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        return $this->validated();
    }
}