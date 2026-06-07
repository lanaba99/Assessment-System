<?php

declare(strict_types=1);

namespace App\Http\Requests\ExamSession;

use App\Domains\ExamSession\Models\CandidateExamStatus;
use Illuminate\Foundation\Http\FormRequest;

class StartSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('start', CandidateExamStatus::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'exam_id' => ['required', 'uuid'],
        ];
    }

    public function examId(): string
    {
        return (string) $this->validated('exam_id');
    }
}
