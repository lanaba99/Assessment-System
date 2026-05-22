<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('create', User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => [
                'required',
                'string',
                Password::min(12)->mixedCase()->letters()->numbers()->symbols(),
                'confirmed',
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'external_employee_id' => ['nullable', 'string', 'max:64'],
            'user_type' => ['required', 'string', 'max:64'],
            'department_id' => ['nullable', 'uuid'],
            'user_attributes' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function profile(): array
    {
        $v = $this->validated();

        return [
            'first_name' => $v['first_name'],
            'last_name' => $v['last_name'],
            'external_employee_id' => $v['external_employee_id'] ?? null,
            'user_type' => $v['user_type'],
            'department_id' => $v['department_id'] ?? null,
            'user_attributes' => $v['user_attributes'] ?? null,
        ];
    }

    public function emailValue(): string
    {
        return (string) $this->validated('email');
    }

    public function plaintextPassword(): string
    {
        return (string) $this->validated('password');
    }
}
