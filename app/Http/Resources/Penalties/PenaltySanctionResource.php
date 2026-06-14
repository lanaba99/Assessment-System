<?php

declare(strict_types=1);

namespace App\Http\Resources\Penalties;

use App\Domains\Penalties\Models\PenaltySanction;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read PenaltySanction $resource
 */
class PenaltySanctionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $sanction = $this->resource;

        return [
            'sanction_id' => (string) $sanction->sanction_id,
            'session_id' => (string) $sanction->session_id,
            'candidate_user_id' => (string) $sanction->candidate_user_id,
            'penalty_rule_id' => (string) $sanction->penalty_rule_id,
            'sanction_type' => (string) $sanction->sanction_type,
            'sanction_amount' => (float) $sanction->sanction_amount,
            'sanction_reason' => (string) $sanction->sanction_reason,
            'sanction_applied_at' => $sanction->sanction_applied_at?->format(DateTimeInterface::ATOM),
            'sanction_metadata' => $sanction->sanction_metadata,
        ];
    }
}
