<?php

declare(strict_types=1);

namespace App\Http\Resources\Penalties;

use App\Domains\Penalties\Models\PenaltyRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read PenaltyRule $resource
 */
class PenaltyRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $rule = $this->resource;

        return [
            'penalty_rule_id' => (string) $rule->penalty_rule_id,
            'penalty_name' => (string) $rule->penalty_name,
            'penalty_type' => (string) $rule->penalty_type,
            'trigger_condition' => (string) $rule->trigger_condition,
            'trigger_parameters' => $rule->trigger_parameters,
            'penalty_points' => $rule->penalty_points !== null ? (float) $rule->penalty_points : null,
            'penalty_percentage' => $rule->penalty_percentage !== null ? (float) $rule->penalty_percentage : null,
            'is_cumulative' => (bool) $rule->is_cumulative,
            'is_active' => (bool) $rule->is_active,
            'penalty_metadata' => $rule->penalty_metadata,
        ];
    }
}
