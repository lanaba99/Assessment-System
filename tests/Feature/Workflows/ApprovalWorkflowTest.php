<?php

declare(strict_types=1);

use App\Domains\Grading\DTOs\AssessmentSummary;
use App\Domains\Grading\Models\AssessmentResult;
use App\Domains\Workflows\Models\ApprovalWorkflow;
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

it('blocks result publication until the approval workflow is approved', function (): void {
    ['candidate' => $candidate, 'exam' => $exam, 'session' => $session] = $this->prepareGradingSession();
    $approver = $this->createUser($this->tenantA);
    $publisher = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($publisher, ['grading.publish', 'workflows.manage', 'workflows.approve']);
    $this->grantPermissionsToUser($approver, ['workflows.approve']);

    $this->createGrade(
        $this->tenantA,
        (string) $session->session_id,
        (string) $candidate->id,
        (string) $exam->exam_id,
        ['is_final_grade' => true, 'finalized_at' => now()],
    );
    $result = $this->createAssessmentResult(
        $this->tenantA,
        (string) $session->session_id,
        (string) $candidate->id,
        (string) $exam->exam_id,
        ['result_status' => AssessmentSummary::STATUS_FINAL],
    );

    Sanctum::actingAs($publisher);

    $initiate = $this->postJson('/api/v1/workflows', [
        'resource_type' => AssessmentResult::class,
        'resource_id' => (string) $result->result_id,
        'workflow_type' => 'result_publication',
    ])->assertCreated();

    $workflowId = $initiate->json('data.workflow_id');

    $this->postJson('/api/v1/exam-sessions/' . $session->session_id . '/result/publish')
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'workflow_not_approved');

    Sanctum::actingAs($approver);
    $this->postJson('/api/v1/workflows/' . $workflowId . '/approve')
        ->assertOk()
        ->assertJsonPath('data.current_workflow_status', 'approved');

    Sanctum::actingAs($publisher);
    $this->postJson('/api/v1/exam-sessions/' . $session->session_id . '/result/publish')
        ->assertOk()
        ->assertJsonPath('data.status.publication_status', 'published');
});

it('creates an approval workflow record for a result publication request', function (): void {
    ['candidate' => $candidate, 'exam' => $exam, 'session' => $session] = $this->prepareGradingSession();
    $admin = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($admin, ['workflows.manage']);
    Sanctum::actingAs($admin);

    $result = $this->createAssessmentResult(
        $this->tenantA,
        (string) $session->session_id,
        (string) $candidate->id,
        (string) $exam->exam_id,
    );

    $this->postJson('/api/v1/workflows', [
        'resource_type' => AssessmentResult::class,
        'resource_id' => (string) $result->result_id,
        'workflow_type' => 'result_publication',
    ])->assertCreated()
        ->assertJsonPath('data.workflow_type', 'result_publication')
        ->assertJsonPath('data.current_workflow_status', 'pending');

    expect(ApprovalWorkflow::query()->count())->toBe(1);
});
