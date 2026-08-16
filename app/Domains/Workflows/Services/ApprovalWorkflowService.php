<?php

declare(strict_types=1);

namespace App\Domains\Workflows\Services;

use App\Domains\Workflows\Models\ApprovalWorkflow;
use App\Domains\Workflows\Models\WorkflowHistory;
use App\Domains\Workflows\Repositories\ApprovalWorkflowRepository;
use RuntimeException;

class ApprovalWorkflowService
{
    public const TYPE_RESULT_PUBLICATION = 'result_publication';

    public function __construct(
        private readonly ApprovalWorkflowRepository $workflows,
    ) {
    }

    public function initiate(
        string $tenantId,
        string $initiatedByUserId,
        string $resourceType,
        string $resourceId,
        string $workflowType,
    ): ApprovalWorkflow {
        $existing = $this->workflows->findPendingForResource(
            $tenantId,
            $resourceType,
            $resourceId,
            $workflowType,
        );

        if ($existing !== null) {
            return $existing;
        }

        return $this->workflows->create([
            'tenant_id' => $tenantId,
            'initiated_by_user_id' => $initiatedByUserId,
            'resource_id' => $resourceId,
            'resource_type' => $resourceType,
            'workflow_type' => $workflowType,
            'current_workflow_status' => 'pending',
            'workflow_initiated_at' => now(),
            'workflow_metadata' => [],
        ]);
    }

    public function approve(
        string $tenantId,
        string $workflowId,
        string $approvedByUserId,
    ): ApprovalWorkflow {
        $workflow = $this->requirePendingWorkflow($tenantId, $workflowId);

        $now = now();
        $metadata = is_array($workflow->workflow_metadata)
            ? $workflow->workflow_metadata
            : [];

        $metadata['approved_by_user_id'] = $approvedByUserId;
        $metadata['approved_at'] = $now->toIso8601String();

        $updated = $this->workflows->update($workflow, [
            'current_workflow_status' => 'approved',
            'workflow_completed_at' => $now,
            'workflow_metadata' => $metadata,
        ]);

        $this->recordHistory(
            workflow: $updated,
            actorUserId: $approvedByUserId,
            actionType: 'approved',
            oldState: 'pending',
            newState: 'approved',
            metadata: ['approved_at' => $now->toIso8601String()],
        );

        return $updated;
    }

    public function reject(
        string $tenantId,
        string $workflowId,
        string $rejectedByUserId,
        string $reason,
    ): ApprovalWorkflow {
        $workflow = $this->requirePendingWorkflow($tenantId, $workflowId);

        $now = now();
        $metadata = is_array($workflow->workflow_metadata)
            ? $workflow->workflow_metadata
            : [];

        $metadata['rejected_by_user_id'] = $rejectedByUserId;
        $metadata['rejected_at'] = $now->toIso8601String();
        $metadata['rejection_reason'] = $reason;

        $updated = $this->workflows->update($workflow, [
            'current_workflow_status' => 'rejected',
            'workflow_completed_at' => $now,
            'workflow_metadata' => $metadata,
        ]);

        $this->recordHistory(
            workflow: $updated,
            actorUserId: $rejectedByUserId,
            actionType: 'rejected',
            oldState: 'pending',
            newState: 'rejected',
            metadata: [
                'rejected_at' => $now->toIso8601String(),
                'reason' => $reason,
            ],
        );

        return $updated;
    }

    public function isPublicationApprovedForResult(string $tenantId, string $resultId): bool
    {
        $pending = $this->workflows->findPendingForResource(
            $tenantId,
            'assessment_result',
            $resultId,
            self::TYPE_RESULT_PUBLICATION,
        );

        if ($pending !== null) {
            return false;
        }

        $workflows = $this->workflows->findForResource(
            $tenantId,
            'assessment_result',
            $resultId,
            self::TYPE_RESULT_PUBLICATION,
        );

        if ($workflows->isEmpty()) {
            return true;
        }

        return $workflows->contains(
            fn (ApprovalWorkflow $workflow): bool => $workflow->current_workflow_status === 'approved',
        );
    }

    public function find(string $tenantId, string $workflowId): ?ApprovalWorkflow
    {
        return $this->workflows->findById($tenantId, $workflowId);
    }

    private function requirePendingWorkflow(string $tenantId, string $workflowId): ApprovalWorkflow
    {
        $workflow = $this->requireWorkflow($tenantId, $workflowId);

        if ($workflow->current_workflow_status !== 'pending') {
            throw new RuntimeException('Only pending workflows can be approved or rejected.');
        }

        return $workflow;
    }

    private function recordHistory(
        ApprovalWorkflow $workflow,
        string $actorUserId,
        string $actionType,
        string $oldState,
        string $newState,
        array $metadata,
    ): void {
        WorkflowHistory::query()->create([
            'workflow_id' => (string) $workflow->workflow_id,
            'actor_user_id' => $actorUserId,
            'action_type' => $actionType,
            'old_state' => $oldState,
            'new_state' => $newState,
            'transition_metadata' => $metadata,
        ]);
    }

    private function requireWorkflow(string $tenantId, string $workflowId): ApprovalWorkflow
    {
        $workflow = $this->workflows->findById($tenantId, $workflowId);

        if ($workflow === null) {
            throw new RuntimeException("Workflow [{$workflowId}] not found.");
        }

        return $workflow;
    }
}
