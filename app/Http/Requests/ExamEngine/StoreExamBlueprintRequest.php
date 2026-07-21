<?php

declare(strict_types=1);

namespace App\Http\Requests\ExamEngine;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExamBlueprintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'section_id' => ['required', 'uuid', Rule::exists('exam_sections', 'section_id')],
            'competency_id' => ['required', 'uuid', Rule::exists('competencies', 'competency_id')],
            'min_questions_count' => ['required', 'integer', 'min:1'],
            'max_questions_count' => ['required', 'integer', 'gte:min_questions_count'],
            'min_weight_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'max_weight_percentage' => ['required', 'numeric', 'gte:min_weight_percentage', 'max:100'],
            'target_difficulty' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'min_discrimination' => ['sometimes', 'numeric', 'min:0', 'max:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'min_discrimination' => $this->input('min_discrimination', 0),
        ]);
    }
}