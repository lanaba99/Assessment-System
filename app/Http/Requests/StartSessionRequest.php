<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'candidate_id' => ['required', 'uuid'],
            'exam_id' => ['required', 'uuid'],
        ];
    }

    public function candidateId(): string
    {
        return (string) $this->validated('candidate_id');
    }

    public function examId(): string
    {
        return (string) $this->validated('exam_id');
    }
}
