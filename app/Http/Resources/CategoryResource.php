<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\QuestionBank\Models\QuestionBank;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuestionBank
 */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->category_id,
            'tenant_id' => (string) $this->tenant_id,
            'title' => (string) $this->category_name,
            'parent_id' => $this->parent_category_id,
            'category_code' => (string) $this->category_code,
            'description' => $this->category_description,
            'hierarchy_level' => (int) $this->hierarchy_level,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
