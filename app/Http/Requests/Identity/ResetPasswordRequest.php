<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Repositories\UserRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        if ($actor === null) {
            return false;
        }

        $target = $this->resolveTargetUser();
        if ($target === null) {
            return false;
        }

        if ((string) $target->tenant_id !== (string) $actor->tenant_id) {
            return false;
        }

        return $actor->can('resetPassword', $target);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'new_password' => [
                'required',
                'string',
                Password::min(12)->mixedCase()->letters()->numbers()->symbols(),
                'confirmed',
            ],
        ];
    }

    public function newPassword(): string
    {
        return (string) $this->validated('new_password');
    }

    private function resolveTargetUser(): ?User
    {
        $userId = (string) $this->route('userId');
        $tenantId = (string) $this->user()?->tenant_id;

        if ($userId === '' || $tenantId === '') {
            return null;
        }

        return app(UserRepository::class)->findById($tenantId, $userId);
    }
}
