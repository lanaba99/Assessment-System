<?php

declare(strict_types=1);

namespace App\Http\Requests\Rules;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEligibilityChainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
        // ownership/permission check happens in the controller via $this->authorize('update', $chain)
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'chain_step_number' => ['sometimes', 'integer', 'min:1'],
            'prerequisite_exam_id' => ['sometimes', 'nullable', 'uuid'],
            'condition_type' => ['sometimes', 'string', 'max:100'],
            'condition_data' => ['sometimes', 'nullable', 'array'],
            'logical_operator' => ['sometimes', 'nullable', 'string', 'in:AND,OR'],
            'min_score_required' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'is_satisfied_override_available' => ['sometimes', 'boolean'],
            'chain_metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}