<?php

declare(strict_types=1);

namespace App\Http\Requests\QuestionBank;

use App\Domains\QuestionBank\Models\Category;
use App\Domains\QuestionBank\Repositories\CategoryRepository;
use Illuminate\Foundation\Http\FormRequest;

class MoveCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $tenantId = (string) tenant()->getKey();
        $category = app(CategoryRepository::class)->findById($tenantId, (string) $this->route('id'));

        if ($category === null) {
            return false;
        }

        return $user->can('update', $category);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'uuid'],
        ];
    }

    public function parentId(): ?string
    {
        $parentId = $this->validated('parent_id');

        return $parentId !== null ? (string) $parentId : null;
    }

    public function category(): Category
    {
        $tenantId = (string) tenant()->getKey();

        return app(CategoryRepository::class)->findById($tenantId, (string) $this->route('id'))
            ?? abort(404, 'Category not found.');
    }
}
