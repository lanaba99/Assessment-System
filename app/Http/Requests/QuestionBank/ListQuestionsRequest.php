<?php

declare(strict_types=1);

namespace App\Http\Requests\QuestionBank;

use App\Domains\QuestionBank\Enums\BloomLevel;
use App\Domains\QuestionBank\Models\Question;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListQuestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('viewAny', Question::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'uuid'],
            'bloom_level' => ['nullable', 'integer', Rule::in(BloomLevel::values())],
            'type' => ['nullable', 'string', Rule::in(['mcq', 'essay', 'true_false', 'short_answer'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array{category_id?: string, bloom_level?: int, type?: string}
     */
    public function filters(): array
    {
        $filters = [];

        if ($this->filled('category_id')) {
            $filters['category_id'] = (string) $this->validated('category_id');
        }

        if ($this->filled('bloom_level')) {
            $filters['bloom_level'] = (int) $this->validated('bloom_level');
        }

        if ($this->filled('type')) {
            $filters['type'] = (string) $this->validated('type');
        }

        return $filters;
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 15);
    }
}
