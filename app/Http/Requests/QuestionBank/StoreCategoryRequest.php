<?php

declare(strict_types=1);

namespace App\Http\Requests\QuestionBank;

use App\Domains\QuestionBank\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('create', Category::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'uuid'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function title(): string
    {
        return (string) $this->validated('title');
    }

    public function parentId(): ?string
    {
        $parentId = $this->validated('parent_id');

        return $parentId !== null ? (string) $parentId : null;
    }

    public function description(): ?string
    {
        $description = $this->validated('description');

        return $description !== null ? (string) $description : null;
    }
}
