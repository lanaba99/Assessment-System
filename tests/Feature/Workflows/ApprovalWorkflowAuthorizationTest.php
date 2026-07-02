<?php

declare(strict_types=1);

use App\Domains\Grading\DTOs\AssessmentSummary;
use App\Domains\Grading\Models\AssessmentResult;
use App\Domains\Workflows\Models\ApprovalWorkflow;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Grading\UsesGradingSchema;
use Tests\Feature\Workflows\UsesWorkflowsSchema;

uses(UsesGradingSchema::class, UsesWorkflowsSchema::class);

beforeEach(function (): void {
    $this->bootGradingSchema();
    $this->migrateWorkflowTables();
    $this->withoutTenancyIdentificationMiddleware();
    $this->initializeTenantContext($this->tenantA);
});

it('denies initiating a workflow when the actor lacks workflows.manage', function (): void {
    $user = $this->createUser($this->tenantA, password: 'UserPass1!');
    // no permissions granted
    Sanctum::actingAs($user);

    ['candidate' => $candidate, 'exam' => $exam, 'session' => $session] = $this->prepareGradingSession();
    $result = $this->createAssessmentResult(
        $this->tenantA,
        (string) $session->session_id,
        (string) $candidate->id,
        (string) $exam->exam_id,
        ['result_status' => AssessmentSummary::STATUS_FINAL],
    );

    $this->postJson('/api/v1/workflows', [
        'resource_type' => AssessmentResult::class,
        'resource_id' => (string) $result->result_id,
        'workflow_type' => 'result_publication',
    ])->assertForbidden();
});

it('denies approving a workflow when the actor lacks workflows.approve', function (): void {
    $manager = $this->createUser($this->tenantA, password: 'ManagerPass1!');
    $this->grantPermissionsToUser($manager, ['workflows.manage']);
    Sanctum::actingAs($manager);

    ['candidate' => $candidate, 'exam' => $exam, 'session' => $session] = $this->prepareGradingSession();
    $result = $this->createAssessmentResult(
        $this->tenantA,
        (string) $session->session_id,
        (string) $candidate->id,
        (string) $exam->exam_id,
        ['result_status' => AssessmentSummary::STATUS_FINAL],
    );

    $initiate = $this->postJson('/api/v1/workflows', [
        'resource_type' => AssessmentResult::class,
        'resource_id' => (string) $result->result_id,
        'workflow_type' => 'result_publication',
    ])->assertCreated();

    $workflowId = $initiate->json('data.workflow_id');

    // manager has workflows.manage but NOT workflows.approve — must be denied
    $this->postJson("/api/v1/workflows/{$workflowId}/approve")
        ->assertForbidden();
});

it('denies viewing a workflow that belongs to another tenant', function (): void {
    $this->initializeTenantContext($this->tenantB);
    $adminB = $this->createUser($this->tenantB, password: 'AdminBPass1!');
    $workflowB = ApprovalWorkflow::query()->forceCreate([
        'workflow_id' => (string) Str::uuid(),
        'tenant_id' => $this->tenantB,
        'initiated_by_user_id' => (string) $adminB->id,
        'resource_id' => (string) Str::uuid(),
        'resource_type' => AssessmentResult::class,
        'workflow_type' => 'result_publication',
        'current_workflow_status' => 'pending',
        'workflow_initiated_at' => now(),
    ]);

    $this->initializeTenantContext($this->tenantA);
    $actorA = $this->createUser($this->tenantA, password: 'ActorPass1!');
    $this->grantPermissionsToUser($actorA, ['workflows.manage', 'workflows.approve']);
    Sanctum::actingAs($actorA);

    $this->getJson("/api/v1/workflows/{$workflowB->workflow_id}")
        ->assertNotFound();
});