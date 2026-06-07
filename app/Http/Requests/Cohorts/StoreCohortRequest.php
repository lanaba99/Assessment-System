<?php

declare(strict_types=1);

namespace App\Http\Requests\Cohorts;

use App\Domains\Cohorts\DTOs\CreateCohortCommand;
use App\Domains\Cohorts\Models\Cohort;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCohortRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('create', Cohort::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cohort_name' => ['required', 'string', 'max:255'],
            'cohort_code' => ['required', 'string', 'max:50', 'unique:cohorts,cohort_code'],
            'cohort_type' => ['required', 'string', Rule::in([
                'team', 'department', 'batch', 'class', 'cohort', 'group',
            ])],
            'cohort_description' => ['nullable', 'string'],
            'parent_cohort_id' => ['nullable', 'uuid'],
            'cohort_attributes' => ['nullable', 'array'],
        ];
    }

    public function toCommand(string $tenantId, string $createdByUserId): CreateCohortCommand
    {
        $validated = $this->validated();

        return new CreateCohortCommand(
            tenantId: $tenantId,
            createdByUserId: $createdByUserId,
            cohortName: (string) $validated['cohort_name'],
            cohortCode: (string) $validated['cohort_code'],
            cohortType: (string) $validated['cohort_type'],
            cohortDescription: isset($validated['cohort_description']) ? (string) $validated['cohort_description'] : null,
            parentCohortId: isset($validated['parent_cohort_id']) ? (string) $validated['parent_cohort_id'] : null,
            cohortAttributes: $validated['cohort_attributes'] ?? null,
        );
    }
}
