<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
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
            'user_type' => ['sometimes', 'string', 'max:64'],
            'department_id' => ['nullable', 'uuid'],
            'status' => ['sometimes', 'string', 'max:32'],
            'user_attributes' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        return array_intersect_key(
            $this->validated(),
            array_flip(['first_name', 'last_name', 'external_employee_id', 'user_type', 'department_id', 'status', 'user_attributes'])
        );
    }
}
