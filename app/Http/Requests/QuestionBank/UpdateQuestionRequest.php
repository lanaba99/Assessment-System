<?php

declare(strict_types=1);

namespace App\Http\Requests\QuestionBank;

use App\Domains\QuestionBank\Enums\BloomLevel;
use App\Domains\QuestionBank\Models\Question;
use App\Domains\QuestionBank\Repositories\QuestionRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $question = app(QuestionRepository::class)->findById((string) $this->route('id'));

        if ($question === null) {
            return false;
        }

        return $user->can('update', $question);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'category_id' => ['sometimes', 'uuid'],
            'bloom_level' => ['sometimes', 'integer', Rule::in(BloomLevel::values())],
            'difficulty_level' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'question_text' => ['sometimes', 'string'],
            'stem' => ['nullable', 'string'],
            'choices' => ['sometimes', 'array', 'min:2'],
            'choices.*.option_text' => ['required_with:choices', 'string'],
            'choices.*.is_correct' => ['required_with:choices', 'boolean'],
            'choices.*.option_sequence' => ['nullable', 'integer', 'min:1'],

            'correct_answer' => ['sometimes', 'boolean'],
            'accepted_answers' => ['sometimes', 'array', 'min:1'],
            'accepted_answers.*' => ['required_with:accepted_answers', 'string', 'max:1000'],
            'match_mode' => ['sometimes', 'string', Rule::in(['exact', 'case_insensitive'])],
            'evaluator_instructions' => ['sometimes', 'array'],

            'psychometrics.p_value' => ['nullable', 'numeric', 'between:0,1'],
            'psychometrics.discrimination_index' => ['nullable', 'numeric', 'between:-1,1'],
            'psychometrics.usage_count' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function question(): Question
    {
        return app(QuestionRepository::class)->findById((string) $this->route('id'))
            ?? abort(404, 'Question not found.');
    }

    /**
     * @return array<string, mixed>
     */
    public function questionAttributes(): array
    {
        $mapped = [];
        $validated = $this->validated();

        if (array_key_exists('title', $validated)) {
            $mapped['question_title'] = $validated['title'];
        }

        if (array_key_exists('category_id', $validated)) {
            $mapped['category_id'] = $validated['category_id'];
        }

        if (array_key_exists('bloom_level', $validated)) {
            $mapped['cognitive_level'] = $validated['bloom_level'];
        }

        if (array_key_exists('difficulty_level', $validated)) {
            $mapped['difficulty_level'] = $validated['difficulty_level'];
        }

        if (isset($validated['psychometrics']['usage_count'])) {
            $mapped['total_usage_count'] = $validated['psychometrics']['usage_count'];
        }

        return $mapped;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function versionAttributes(): ?array
    {
        $validated = $this->validated();
        $mapped = [];

        if (array_key_exists('question_text', $validated)) {
            $mapped['question_text'] = $validated['question_text'];
        }

        if (array_key_exists('stem', $validated)) {
            $mapped['question_stem'] = $validated['stem'];
        }

        return $mapped === [] ? null : $mapped;
    }

    /**
     * @return array<int, array{option_text: string, is_correct: bool, option_sequence?: int}>|null
     */
    public function choices(): ?array
    {
        if (! $this->has('choices')) {
            return null;
        }

        return $this->validated('choices');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function psychometrics(): ?array
    {
        if (! $this->has('psychometrics')) {
            return null;
        }

        return $this->validated('psychometrics') ?? [];
    }

    /**
     * Type-specific answer payload. Null when the request carries no answer
     * change (so the service preserves the current version's answer).
     *
     * @return array<string, mixed>|null
     */
    public function answer(): ?array
    {
        $answer = [];

        if ($this->has('correct_answer')) {
            $answer['correct_answer'] = $this->boolean('correct_answer');
        }

        if ($this->has('accepted_answers')) {
            $answer['accepted_answers'] = $this->validated('accepted_answers') ?? [];
            $answer['match_mode'] = $this->validated('match_mode') ?? 'case_insensitive';
        }

        return $answer === [] ? null : $answer;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function evaluatorInstructions(): ?array
    {
        if (! $this->has('evaluator_instructions')) {
            return null;
        }

        return $this->validated('evaluator_instructions') ?? [];
    }
}
