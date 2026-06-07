<?php

declare(strict_types=1);

namespace App\Http\Resources\Cohorts;

use App\Domains\Cohorts\Models\CohortMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CohortMember
 */
class CohortMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->member_id,
            'cohort_id' => (string) $this->cohort_id,
            'user_id' => (string) $this->user_id,
            'tenant_id' => (string) $this->tenant_id,
            'membership_role' => (string) $this->membership_role,
            'added_at' => $this->added_at?->toIso8601String(),
            'removed_at' => $this->removed_at?->toIso8601String(),
            'is_active_member' => (bool) $this->is_active_member,
        ];
    }
}
