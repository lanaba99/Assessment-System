<?php

declare(strict_types=1);

namespace App\Domains\Workflows\Policies;

use App\Domains\Identity\Contracts\AuthorizationService;
use App\Domains\Identity\Models\User;
use App\Domains\Workflows\Models\ApprovalWorkflow;

class ApprovalWorkflowPolicy
{
    public function __construct(
        private readonly AuthorizationService $auth,
    ) {
    }

    public function initiate(User $actor): bool
    {
        return $this->hasPermission($actor, 'workflows.manage');
    }

    /**
     * Can the actor list workflows (GET /workflows)?
     * Same gate as viewing a single workflow: either side of the SoD split
     * (the initiator role via workflows.manage, or the approver role via
     * workflows.approve) can browse the list — they just can't both
     * initiate AND approve the same workflow (enforced in initiate/approve).
     */
    public function viewAny(User $actor): bool
    {
        return $this->hasPermission($actor, 'workflows.manage')
            || $this->hasPermission($actor, 'workflows.approve');
    }

    public function view(User $actor, ApprovalWorkflow $workflow): bool
    {
        return $this->sameTenant($actor, $workflow)
            && ($this->hasPermission($actor, 'workflows.manage')
                || $this->hasPermission($actor, 'workflows.approve'));
    }

    /**
     * Separation of Duties (SoD): approval is intentionally gated by a
     * DIFFERENT permission (`workflows.approve`) than initiation
     * (`workflows.manage`). A role that authors/initiates a workflow
     * (e.g. Technical Evaluator) must not also be able to approve its
     * own request — approval requires an independent reviewer
     * (Tenant Admin). Do not grant both permissions to the same role.
     */

    public function approve(User $actor, ApprovalWorkflow $workflow): bool
    {
        return $this->sameTenant($actor, $workflow)
            && $this->hasPermission($actor, 'workflows.approve');
    }

    /**
     * Rejection is gated by the same permission as approval
     * (`workflows.approve`) — whoever is authorized to approve a
     * workflow is also the one authorized to reject it.
     */
    public function reject(User $actor, ApprovalWorkflow $workflow): bool
    {
        return $this->sameTenant($actor, $workflow)
            && $this->hasPermission($actor, 'workflows.approve');
    }

    private function sameTenant(User $actor, ApprovalWorkflow $workflow): bool
    {
        return (string) $actor->tenant_id === (string) $workflow->tenant_id;
    }

    private function hasPermission(User $actor, string $permission): bool
    {
        return $this->auth->userHasPermission(
            (string) $actor->tenant_id,
            (string) $actor->id,
            $permission,
        );
    }
}