<?php

declare(strict_types=1);

namespace App\Http\Requests\QuestionBank;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePsychometricsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'difficulty_index' => ['required', 'numeric', 'min:0', 'max:1'],
            'discrimination_index' => ['required', 'numeric', 'min:0', 'max:1'],
            'sample_size' => ['required', 'integer', 'min:1'],
            'correct_count' => ['required', 'integer', 'min:0'],
        ];
    }
}