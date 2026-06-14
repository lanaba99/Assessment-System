<?php

declare(strict_types=1);

namespace App\Http\Resources\Workflows;

use App\Domains\Workflows\Models\ApprovalWorkflow;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read ApprovalWorkflow $resource
 */
class ApprovalWorkflowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $workflow = $this->resource;

        return [
            'workflow_id' => (string) $workflow->workflow_id,
            'resource_type' => (string) $workflow->resource_type,
            'resource_id' => (string) $workflow->resource_id,
            'workflow_type' => (string) $workflow->workflow_type,
            'current_workflow_status' => (string) $workflow->current_workflow_status,
            'current_stage_key' => $workflow->current_stage_key,
            'workflow_initiated_at' => $workflow->workflow_initiated_at?->format(DateTimeInterface::ATOM),
            'workflow_completed_at' => $workflow->workflow_completed_at?->format(DateTimeInterface::ATOM),
            'workflow_metadata' => $workflow->workflow_metadata,
        ];
    }
}
