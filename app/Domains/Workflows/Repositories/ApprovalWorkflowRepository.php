<?php

declare(strict_types=1);

namespace App\Domains\Workflows\Repositories;

use App\Domains\Workflows\Models\ApprovalWorkflow;
use Illuminate\Support\Collection;

class ApprovalWorkflowRepository
{
    public function __construct(
        private readonly ApprovalWorkflow $model,
    ) {
    }

    public function findById(string $tenantId, string $workflowId): ?ApprovalWorkflow
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('workflow_id', $workflowId)
            ->first();
    }

    /**
     * @return Collection<int, ApprovalWorkflow>
     */
    public function findForResource(
        string $tenantId,
        string $resourceType,
        string $resourceId,
        string $workflowType,
    ): Collection {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->where('workflow_type', $workflowType)
            ->orderByDesc('workflow_initiated_at')
            ->get();
    }

    public function findPendingForResource(
        string $tenantId,
        string $resourceType,
        string $resourceId,
        string $workflowType,
    ): ?ApprovalWorkflow {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->where('workflow_type', $workflowType)
            ->where('current_workflow_status', 'pending')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ApprovalWorkflow
    {
        return $this->model->newQuery()->forceCreate($attributes);
    }

    public function update(ApprovalWorkflow $workflow, array $attributes): ApprovalWorkflow
    {
        $workflow->forceFill($attributes)->save();

        return $workflow;
    }
}
