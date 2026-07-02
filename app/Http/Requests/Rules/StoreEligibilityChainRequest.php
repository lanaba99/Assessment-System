<?php

declare(strict_types=1);

namespace App\Http\Requests\Rules;

use App\Domains\Rules\Models\EligibilityChain;
use Illuminate\Foundation\Http\FormRequest;

class StoreEligibilityChainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', EligibilityChain::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'exam_id' => ['required', 'uuid'],
            'chain_step_number' => ['required', 'integer', 'min:1'],
            'prerequisite_exam_id' => ['nullable', 'uuid'],
            'condition_type' => ['required', 'string', 'max:100'],
            'condition_data' => ['nullable', 'array'],
            'logical_operator' => ['nullable', 'string', 'in:AND,OR'],
            'min_score_required' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_satisfied_override_available' => ['nullable', 'boolean'],
            'chain_metadata' => ['nullable', 'array'],
        ];
    }
}