<?php

declare(strict_types=1);

namespace App\Http\Requests\QuestionBank;

use App\Domains\QuestionBank\Enums\BloomLevel;
use App\Domains\QuestionBank\Models\Question;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('create', Question::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'uuid'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(['mcq', 'essay', 'true_false', 'short_answer'])],
            'question_text' => ['required', 'string'],
            'stem' => ['nullable', 'string'],
            'bloom_level' => ['required', 'integer', Rule::in(BloomLevel::values())],
            'difficulty_level' => ['nullable', 'integer', 'min:1', 'max:5'],
            'choices' => ['required_if:type,mcq', 'array', 'min:2'],
            'choices.*.option_text' => ['required_with:choices', 'string'],
            'choices.*.is_correct' => ['required_with:choices', 'boolean'],
            'choices.*.option_sequence' => ['nullable', 'integer', 'min:1'],
            'psychometrics.p_value' => ['nullable', 'numeric', 'between:0,1'],
            'psychometrics.discrimination_index' => ['nullable', 'numeric', 'between:-1,1'],
            'psychometrics.usage_count' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function categoryId(): string
    {
        return (string) $this->validated('category_id');
    }

    public function title(): string
    {
        return (string) $this->validated('title');
    }

    public function type(): string
    {
        return (string) $this->validated('type');
    }

    public function questionText(): string
    {
        return (string) $this->validated('question_text');
    }

    public function stem(): ?string
    {
        $stem = $this->validated('stem');

        return $stem !== null ? (string) $stem : null;
    }

    public function bloomLevel(): int
    {
        return (int) $this->validated('bloom_level');
    }

    public function difficultyLevel(): int
    {
        return (int) ($this->validated('difficulty_level') ?? 1);
    }

    /**
     * @return array<int, array{option_text: string, is_correct: bool, option_sequence?: int}>
     */
    public function choices(): array
    {
        return $this->validated('choices') ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function psychometrics(): array
    {
        return $this->validated('psychometrics') ?? [];
    }
}
