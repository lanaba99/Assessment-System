<?php

declare(strict_types=1);

use App\Domains\Grading\DTOs\AssessmentSummary;
use App\Domains\Workflows\Models\WorkflowHistory;
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

function initiateWorkflowForHistoryTest($test): array
{
    ['candidate' => $candidate, 'exam' => $exam, 'session' => $session] = $test->prepareGradingSession();
    $admin = $test->createUser($test->tenantA);
    $test->grantPermissionsToUser($admin, ['grading.publish', 'workflows.manage', 'workflows.approve']);

    $test->createAssessmentResult(
        $test->tenantA,
        (string) $session->session_id,
        (string) $candidate->id,
        (string) $exam->exam_id,
        ['result_status' => AssessmentSummary::STATUS_FINAL],
    );

    $result = \App\Domains\Grading\Models\AssessmentResult::query()
        ->where('session_id', $session->session_id)
        ->firstOrFail();

    Sanctum::actingAs($admin);

    $initiate = $test->postJson('/api/v1/workflows', [
        'resource_type' => 'assessment_result',
        'resource_id' => (string) $result->result_id,
        'workflow_type' => 'result_publication',
    ])->assertCreated();

    $workflowId = $initiate->json('data.workflow_id');

    return compact('admin', 'workflowId');
}

it('returns paginated history for an authorized actor', function (): void {
    ['admin' => $admin, 'workflowId' => $workflowId] = initiateWorkflowForHistoryTest($this);

    Sanctum::actingAs($admin);
    $this->postJson('/api/v1/workflows/' . $workflowId . '/approve')->assertOk();

    $response = $this->getJson('/api/v1/workflows/' . $workflowId . '/history')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['history_id', 'workflow_id', 'actor_user_id', 'action_type', 'old_state', 'new_state', 'transition_metadata', 'created_at']],
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        ]);

    // initiate + approve = 2 history rows, newest first.
    expect($response->json('meta.total'))->toBe(2);
    expect($response->json('data.0.workflow_id'))->toBe($workflowId);
});

it('returns 401 for an unauthenticated request', function (): void {
    ['workflowId' => $workflowId] = initiateWorkflowForHistoryTest($this);

    $this->getJson('/api/v1/workflows/' . $workflowId . '/history')
        ->assertUnauthorized();
});

it('returns 403 for an actor without workflows.manage or workflows.approve', function (): void {
    ['workflowId' => $workflowId] = initiateWorkflowForHistoryTest($this);

    $outsider = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($outsider, ['grading.view']);
    Sanctum::actingAs($outsider);

    $this->getJson('/api/v1/workflows/' . $workflowId . '/history')
        ->assertForbidden();
});

it('returns 404 for a workflow that does not exist', function (): void {
    $admin = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($admin, ['workflows.manage']);
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/workflows/' . (string) \Illuminate\Support\Str::uuid() . '/history')
        ->assertNotFound();
});

it('returns an empty list when a workflow has no history rows', function (): void {
    ['admin' => $admin, 'workflowId' => $workflowId] = initiateWorkflowForHistoryTest($this);

    // initiate() always writes one row — delete it to prove the endpoint
    // tolerates a genuinely empty collection rather than assuming >=1.
    WorkflowHistory::query()->where('workflow_id', $workflowId)->delete();

    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/workflows/' . $workflowId . '/history')
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.total', 0);
});

it('returns a populated, newest-first history after initiate, approve', function (): void {
    ['admin' => $admin, 'workflowId' => $workflowId] = initiateWorkflowForHistoryTest($this);

    Sanctum::actingAs($admin);
    $this->postJson('/api/v1/workflows/' . $workflowId . '/approve')->assertOk();

    $response = $this->getJson('/api/v1/workflows/' . $workflowId . '/history')->assertOk();

    $actionTypes = collect($response->json('data'))->pluck('action_type')->all();
    expect($actionTypes[0])->toBe('approved');
    expect($actionTypes)->toContain('initiated');
});

it('never returns another tenant workflow history', function (): void {
    ['workflowId' => $workflowId] = initiateWorkflowForHistoryTest($this);

    $this->initializeTenantContext($this->tenantB);
    $userB = $this->createUser($this->tenantB);
    $this->grantPermissionsToUser($userB, ['workflows.manage', 'workflows.approve']);
    Sanctum::actingAs($userB);

    $this->getJson('/api/v1/workflows/' . $workflowId . '/history')
        ->assertNotFound();
});