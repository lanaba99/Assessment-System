<?php

declare(strict_types=1);

namespace App\Http\Requests\ExamEngine;

use Illuminate\Foundation\Http\FormRequest;

class StoreExamSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'section_name' => ['required', 'string', 'max:255'],
            'section_code' => ['nullable', 'string', 'max:50'],
            'section_sequence' => ['required', 'integer', 'min:1'],
            'questions_in_section' => ['required', 'integer', 'min:1'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1'],
        ];
    }
}