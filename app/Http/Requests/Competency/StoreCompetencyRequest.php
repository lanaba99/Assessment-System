<?php

declare(strict_types=1);

namespace App\Http\Requests\Competency;

use App\Domains\Competency\Models\Competency;
use Illuminate\Foundation\Http\FormRequest;

class StoreCompetencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('create', Competency::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'uuid'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function name(): string
    {
        return (string) $this->validated('name');
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
