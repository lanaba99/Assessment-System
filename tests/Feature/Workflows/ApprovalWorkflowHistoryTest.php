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



it('returns paginated history for an authorized actor', function (): void {
    ['admin' => $admin, 'workflowId' => $workflowId] = $this->initiateWorkflowForHistoryTest();

    Sanctum::actingAs($admin);
    $this->postJson('/api/v1/workflows/' . $workflowId . '/approve')->assertOk();

    $response = $this->getJson('/api/v1/workflows/' . $workflowId . '/history')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['history_id', 'workflow_id', 'actor_user_id', 'action_type', 'old_state', 'new_state', 'transition_metadata', 'created_at']],
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        ]);

    // initiate() does not write a history row — only approve()/reject() do.
    expect($response->json('meta.total'))->toBe(1);
    expect($response->json('data.0.workflow_id'))->toBe($workflowId);
    expect($response->json('data.0.action_type'))->toBe('approved');
});

it('returns 401 for an unauthenticated request', function (): void {
    ['workflowId' => $workflowId] = $this->initiateWorkflowForHistoryTest();

    $this->getJson('/api/v1/workflows/' . $workflowId . '/history')
        ->assertUnauthorized();
});

it('returns 403 for an actor without workflows.manage or workflows.approve', function (): void {
    ['workflowId' => $workflowId] = $this->initiateWorkflowForHistoryTest();

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
    ['admin' => $admin, 'workflowId' => $workflowId] = $this->initiateWorkflowForHistoryTest();

    // initiate() writes zero history rows (only approve()/reject() do) — this
    // delete is a no-op given that, but keeps the test explicit about intent.
    WorkflowHistory::query()->where('workflow_id', $workflowId)->delete();

    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/workflows/' . $workflowId . '/history')
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.total', 0);
});

it('records the correct action_type, actor, and state transition after approve', function (): void {
    ['admin' => $admin, 'workflowId' => $workflowId] = $this->initiateWorkflowForHistoryTest();

    Sanctum::actingAs($admin);
    $this->postJson('/api/v1/workflows/' . $workflowId . '/approve')->assertOk();

    $response = $this->getJson('/api/v1/workflows/' . $workflowId . '/history')->assertOk();

    expect($response->json('meta.total'))->toBe(1)
        ->and($response->json('data.0.action_type'))->toBe('approved')
        ->and($response->json('data.0.old_state'))->toBe('pending')
        ->and($response->json('data.0.new_state'))->toBe('approved')
        ->and($response->json('data.0.actor_user_id'))->toBe((string) $admin->id);
});

it('never returns another tenant workflow history', function (): void {
    ['workflowId' => $workflowId] = $this->initiateWorkflowForHistoryTest();

    $this->initializeTenantContext($this->tenantB);
    $userB = $this->createUser($this->tenantB);
    $this->grantPermissionsToUser($userB, ['workflows.manage', 'workflows.approve']);
    Sanctum::actingAs($userB);

    $this->getJson('/api/v1/workflows/' . $workflowId . '/history')
        ->assertNotFound();
});