<?php

declare(strict_types=1);

namespace App\Http\Requests\QuestionBank;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkCompetencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'competency_id' => ['required', 'uuid', Rule::exists('competencies', 'competency_id')],
            'weight_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_primary_competency' => ['sometimes', 'boolean'],
        ];
    }
}