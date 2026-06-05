<?php

declare(strict_types=1);

namespace App\Http\Requests\Competency;

use App\Domains\Competency\Models\Competency;
use App\Domains\Competency\Repositories\CompetencyRepository;
use Illuminate\Foundation\Http\FormRequest;

class MoveCompetencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $tenantId = (string) tenant()->getKey();
        $competency = app(CompetencyRepository::class)->findById($tenantId, (string) $this->route('id'));

        if ($competency === null) {
            return false;
        }

        return $user->can('update', $competency);
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

    public function competency(): Competency
    {
        $tenantId = (string) tenant()->getKey();

        return app(CompetencyRepository::class)->findById($tenantId, (string) $this->route('id'))
            ?? abort(404, 'Competency not found.');
    }
}
