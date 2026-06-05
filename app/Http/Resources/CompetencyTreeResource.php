<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin array<string, mixed>
 */
class CompetencyTreeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $node */
        $node = is_array($this->resource) ? $this->resource : [];

        return [
            'id' => $node['id'] ?? null,
            'name' => $node['name'] ?? null,
            'parent_id' => $node['parent_id'] ?? null,
            'hierarchy_level' => $node['hierarchy_level'] ?? null,
            'is_active' => $node['is_active'] ?? null,
            'children' => collect($node['children'] ?? [])
                ->map(static fn (array $child): CompetencyTreeResource => new self($child))
                ->values()
                ->all(),
        ];
    }
}
