<?php

declare(strict_types=1);

namespace App\Http\Requests\QuestionBank;

use App\Domains\QuestionBank\Enums\BloomLevel;
use App\Domains\QuestionBank\Enums\QuestionType;
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
            'type' => ['required', 'string', Rule::in(QuestionType::values())],
            'question_text' => ['required', 'string'],
            'stem' => ['nullable', 'string'],
            'bloom_level' => ['required', 'integer', Rule::in(BloomLevel::values())],
            'difficulty_level' => ['nullable', 'integer', 'min:1', 'max:5'],

            // MCQ
            'choices' => ['required_if:type,mcq', 'array', 'min:2'],
            'choices.*.option_text' => ['required_with:choices', 'string'],
            'choices.*.is_correct' => ['required_with:choices', 'boolean'],
            'choices.*.option_sequence' => ['nullable', 'integer', 'min:1'],

            // True/False
            'correct_answer' => ['required_if:type,true_false', 'boolean'],

            // Short answer
            'accepted_answers' => ['required_if:type,short_answer', 'array', 'min:1'],
            'accepted_answers.*' => ['required_with:accepted_answers', 'string', 'max:1000'],
            'match_mode' => ['nullable', 'string', Rule::in(['exact', 'case_insensitive'])],

            // Essay / hybrid manual-grading guidance
            'evaluator_instructions' => ['nullable', 'array'],

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

    /**
     * Type-specific answer payload consumed by the QuestionTypeStrategy.
     *
     * @return array<string, mixed>
     */
    public function answer(): array
    {
        $answer = [];

        if ($this->type() === QuestionType::TrueFalse->value) {
            $answer['correct_answer'] = $this->boolean('correct_answer');
        }

        if ($this->type() === QuestionType::ShortAnswer->value) {
            $answer['accepted_answers'] = $this->validated('accepted_answers') ?? [];
            $answer['match_mode'] = $this->validated('match_mode') ?? 'case_insensitive';
        }

        return $answer;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function evaluatorInstructions(): ?array
    {
        return $this->validated('evaluator_instructions');
    }
}
