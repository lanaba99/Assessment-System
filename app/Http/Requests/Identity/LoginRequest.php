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

    /**
     * Fail-closed: a login request without a resolvable tenant is invalid.
     * Either tenancy middleware bound a tenant or the caller passed tenant_id
     * explicitly. Falling through to an empty string would let queries run
     * with `tenant_id = ''` and silently miss every user.
     */
    protected function prepareForValidation(): void
    {
        if ($this->resolveTenantId() === null) {
            abort(422, 'Tenant context could not be resolved. Provide tenant_id explicitly or invoke this endpoint through a tenant-scoped host.');
        }
    }

    public function tenantId(): string
    {
        return (string) $this->resolveTenantId();
    }

    private function resolveTenantId(): ?string
    {
        $explicit = $this->input('tenant_id');
        if (is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        $bound = function_exists('tenant') ? tenant() : null;
        if ($bound === null) {
            return null;
        }

        $key = $bound->getKey();

        return $key === null ? null : (string) $key;
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
