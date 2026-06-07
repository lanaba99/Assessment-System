<?php

declare(strict_types=1);

namespace App\Http\Resources\Cohorts;

use App\Domains\Cohorts\Models\Cohort;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Cohort
 */
class CohortResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->cohort_id,
            'tenant_id' => (string) $this->tenant_id,
            'created_by_user_id' => (string) $this->created_by_user_id,
            'parent_cohort_id' => $this->parent_cohort_id !== null ? (string) $this->parent_cohort_id : null,
            'cohort_name' => (string) $this->cohort_name,
            'cohort_code' => (string) $this->cohort_code,
            'cohort_type' => (string) $this->cohort_type,
            'cohort_description' => $this->cohort_description,
            'hierarchy_level' => (int) $this->hierarchy_level,
            'cohort_attributes' => $this->cohort_attributes,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
