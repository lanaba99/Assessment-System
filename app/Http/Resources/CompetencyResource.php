<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Competency\Models\Competency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Competency
 */
class CompetencyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->competency_id,
            'tenant_id' => (string) $this->tenant_id,
            'name' => (string) $this->competency_name,
            'parent_id' => $this->parent_competency_id,
            'description' => $this->description,
            'hierarchy_level' => (int) $this->hierarchy_level,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
