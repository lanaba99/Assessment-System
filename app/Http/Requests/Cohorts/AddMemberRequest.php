<?php

declare(strict_types=1);

namespace App\Http\Requests\Cohorts;

use App\Domains\Cohorts\DTOs\AddMemberCommand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddMemberRequest extends FormRequest
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
            'user_id' => ['required', 'uuid', Rule::exists('users', 'id')],
            'membership_role' => ['nullable', 'string', Rule::in([
                'member', 'manager', 'coordinator', 'observer',
            ])],
        ];
    }

    public function toCommand(string $tenantId, string $cohortId): AddMemberCommand
    {
        $validated = $this->validated();

        return new AddMemberCommand(
            tenantId: $tenantId,
            cohortId: $cohortId,
            userId: (string) $validated['user_id'],
            membershipRole: isset($validated['membership_role']) ? (string) $validated['membership_role'] : 'member',
        );
    }
}
