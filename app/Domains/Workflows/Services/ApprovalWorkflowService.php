<?php

declare(strict_types=1);

namespace App\Domains\Workflows\Services;

use App\Domains\Grading\Models\AssessmentResult;
use App\Domains\Workflows\Models\ApprovalWorkflow;
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

    public function approve(string $tenantId, string $workflowId, string $approvedByUserId): ApprovalWorkflow
    {
        $workflow = $this->requireWorkflow($tenantId, $workflowId);

        if ($workflow->current_workflow_status !== 'pending') {
            throw new RuntimeException('Only pending workflows can be approved.');
        }

        $metadata = is_array($workflow->workflow_metadata) ? $workflow->workflow_metadata : [];
        $metadata['approved_by_user_id'] = $approvedByUserId;
        $metadata['approved_at'] = now()->toIso8601String();

        return $this->workflows->update($workflow, [
            'current_workflow_status' => 'approved',
            'workflow_completed_at' => now(),
            'workflow_metadata' => $metadata,
        ]);
    }

    public function isPublicationApprovedForResult(string $tenantId, string $resultId): bool
    {
        $pending = $this->workflows->findPendingForResource(
            $tenantId,
            AssessmentResult::class,
            $resultId,
            self::TYPE_RESULT_PUBLICATION,
        );

        if ($pending !== null) {
            return false;
        }

        $workflows = $this->workflows->findForResource(
            $tenantId,
            AssessmentResult::class,
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

    private function requireWorkflow(string $tenantId, string $workflowId): ApprovalWorkflow
    {
        $workflow = $this->workflows->findById($tenantId, $workflowId);

        if ($workflow === null) {
            throw new RuntimeException("Workflow [{$workflowId}] not found.");
        }

        return $workflow;
    }
}
