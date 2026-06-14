<?php

declare(strict_types=1);

namespace App\Http\Requests\Penalties;

use App\Domains\Penalties\Models\PenaltyRule;
use App\Domains\Penalties\Repositories\PenaltyRuleRepository;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePenaltyRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $rule = app(PenaltyRuleRepository::class)->findById(
            (string) tenant()->getKey(),
            (string) $this->route('ruleId'),
        );

        if ($rule === null) {
            abort(404, 'Penalty rule not found.');
        }

        return $user->can('update', $rule);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'penalty_name' => ['sometimes', 'string', 'max:255'],
            'penalty_type' => ['sometimes', 'string', 'max:100'],
            'trigger_condition' => ['sometimes', 'string', 'max:100'],
            'trigger_parameters' => ['nullable', 'array'],
            'penalty_points' => ['nullable', 'numeric', 'min:0'],
            'penalty_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_cumulative' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'penalty_metadata' => ['nullable', 'array'],
        ];
    }
}
