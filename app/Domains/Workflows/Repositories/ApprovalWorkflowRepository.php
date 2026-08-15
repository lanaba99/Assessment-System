<?php

declare(strict_types=1);

namespace App\Domains\Workflows\Repositories;

use App\Domains\Workflows\Models\ApprovalWorkflow;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
     * General-purpose workflow list for the tenant, with optional filters.
     * Backs GET /api/v1/workflows — a single list endpoint filtered by
     * query params, not a separate endpoint per status/type.
     *
     * `initiated_by_user_id` is set by the controller (never taken directly
     * from client-supplied query params) to enforce ownership scoping for
     * actors who only hold workflows.manage — see
     * ApprovalWorkflowController::index() for the scoping rule.
     *
     * @param  array{status?: string, workflow_type?: string, resource_type?: string, resource_id?: string, initiated_by_user_id?: string}  $filters
     */
    public function paginateList(string $tenantId, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId);

        if (! empty($filters['status'])) {
            $query->where('current_workflow_status', $filters['status']);
        }

        if (! empty($filters['workflow_type'])) {
            $query->where('workflow_type', $filters['workflow_type']);
        }

        if (! empty($filters['resource_type'])) {
            $query->where('resource_type', $filters['resource_type']);
        }

        if (! empty($filters['resource_id'])) {
            $query->where('resource_id', $filters['resource_id']);
        }

        if (! empty($filters['initiated_by_user_id'])) {
            $query->where('initiated_by_user_id', $filters['initiated_by_user_id']);
        }

        return $query
            ->orderByDesc('workflow_initiated_at')
            ->paginate($perPage);
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
