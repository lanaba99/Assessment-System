<?php

declare(strict_types=1);

namespace App\Http\Requests\Cohorts;

use App\Domains\Cohorts\DTOs\UpdateCohortCommand;
use App\Domains\Cohorts\Repositories\CohortRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCohortRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $tenantId = (string) tenant()->getKey();
        $cohort = app(CohortRepository::class)
            ->findById($tenantId, (string) $this->route('cohortId'));

        if ($cohort === null) {
            abort(404, 'Cohort not found.');
        }

        return $user->can('update', $cohort);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cohort_name' => ['sometimes', 'string', 'max:255'],
            'cohort_code' => [
                'sometimes', 'string', 'max:50',
                Rule::unique('cohorts', 'cohort_code')
                    ->ignore($this->route('cohortId'), 'cohort_id'),
            ],
            'cohort_type' => ['sometimes', 'string', Rule::in([
                'team', 'department', 'batch', 'class', 'cohort', 'group',
            ])],
            'cohort_description' => ['sometimes', 'nullable', 'string'],
            'cohort_attributes' => ['sometimes', 'nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function toCommand(): UpdateCohortCommand
    {
        $validated = $this->validated();

        return new UpdateCohortCommand(
            cohortName: isset($validated['cohort_name']) ? (string) $validated['cohort_name'] : null,
            cohortCode: isset($validated['cohort_code']) ? (string) $validated['cohort_code'] : null,
            cohortType: isset($validated['cohort_type']) ? (string) $validated['cohort_type'] : null,
            cohortDescription: array_key_exists('cohort_description', $validated)
                ? ($validated['cohort_description'] !== null ? (string) $validated['cohort_description'] : null)
                : null,
            cohortAttributes: array_key_exists('cohort_attributes', $validated) ? $validated['cohort_attributes'] : null,
            isActive: isset($validated['is_active']) ? (bool) $validated['is_active'] : null,
        );
    }
}
