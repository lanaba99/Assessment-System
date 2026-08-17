<?php

declare(strict_types=1);

namespace App\Http\Resources\Workflows;

use App\Domains\Workflows\Models\WorkflowHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkflowHistory
 */
class WorkflowHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'history_id' => (string) $this->history_id,
            'workflow_id' => (string) $this->workflow_id,
            'actor_user_id' => (string) $this->actor_user_id,
            'action_type' => (string) $this->action_type,
            'old_state' => $this->old_state,
            'new_state' => $this->new_state,
            'transition_metadata' => $this->transition_metadata,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}