<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Domains\Identity\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

class CreateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('create', Role::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role_name' => ['required', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:1024'],
            'role_category' => ['required', 'string', 'max:64'],
            'is_custom' => ['sometimes', 'boolean'],
        ];
    }

    public function roleNameValue(): string
    {
        return (string) $this->validated('role_name');
    }

    public function descriptionValue(): ?string
    {
        $value = $this->validated('description');

        return $value === null ? null : (string) $value;
    }

    public function roleCategoryValue(): string
    {
        return (string) $this->validated('role_category');
    }

    public function isCustomValue(): bool
    {
        return (bool) ($this->validated('is_custom') ?? true);
    }
}
