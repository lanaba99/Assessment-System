<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class AcceptInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'token' => ['required', 'string', 'min:32', 'max:128'],
            'password' => [
                'required',
                'string',
                Password::min(12)->mixedCase()->letters()->numbers()->symbols(),
                'confirmed',
            ],
        ];
    }

    public function emailValue(): string
    {
        return (string) $this->validated('email');
    }

    public function tokenValue(): string
    {
        return (string) $this->validated('token');
    }

    public function passwordValue(): string
    {
        return (string) $this->validated('password');
    }
}
