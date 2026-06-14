<?php

declare(strict_types=1);

namespace App\Http\Requests\Penalties;

use App\Domains\Penalties\Models\PenaltyRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePenaltyRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PenaltyRule::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'penalty_name' => ['required', 'string', 'max:255'],
            'penalty_type' => ['required', 'string', 'max:100'],
            'trigger_condition' => ['required', 'string', 'max:100'],
            'trigger_parameters' => ['nullable', 'array'],
            'penalty_points' => ['nullable', 'numeric', 'min:0'],
            'penalty_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_cumulative' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'penalty_metadata' => ['nullable', 'array'],
        ];
    }
}
