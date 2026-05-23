<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // Optional override. In tenant context the subdomain already identifies the
            // tenant; we only accept this when an out-of-band caller needs to be explicit.
            'tenant_id' => ['sometimes', 'uuid'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:1', 'max:512'],
        ];
    }

    public function tenantId(): string
    {
        $explicit = $this->validated('tenant_id');
        if (is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        $bound = function_exists('tenant') ? tenant() : null;
        if ($bound === null) {
            return '';
        }

        return (string) $bound->getKey();
    }

    public function emailOrEmployeeId(): string
    {
        return (string) $this->validated('email');
    }

    public function password(): string
    {
        return (string) $this->validated('password');
    }
}
