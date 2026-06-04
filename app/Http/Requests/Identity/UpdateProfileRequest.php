<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Self-update only. Tightly restricted to non-authorization-bearing fields so
 * a user cannot escalate their own user_type, status, or department via this
 * endpoint. Admin-only fields must be edited through admin-scoped routes that
 * pass the UserPolicy@update authorization gate.
 */
class UpdateProfileRequest extends FormRequest
{
    /** @var array<int, string> */
    public const SELF_EDITABLE_FIELDS = [
        'first_name',
        'last_name',
        'external_employee_id',
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'external_employee_id' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        return array_intersect_key(
            $this->validated(),
            array_flip(self::SELF_EDITABLE_FIELDS),
        );
    }
}
