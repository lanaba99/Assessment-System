<?php

declare(strict_types=1);

use App\Domains\Penalties\Models\PenaltyRule;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Penalties\UsesPenaltiesSchema;

uses(UsesPenaltiesSchema::class);

beforeEach(function (): void {
    $this->bootGradingSchema();
    $this->withoutTenancyIdentificationMiddleware();
    $this->initializeTenantContext($this->tenantA);
});

it('creates a penalty rule when the actor has penalties.manage', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['penalties.manage', 'penalties.view']);
    Sanctum::actingAs($admin);

    $response = $this->postJson('/api/v1/penalty-rules', [
        'penalty_name' => 'Multiple Faces Detected',
        'penalty_type' => 'penalty',
        'trigger_condition' => 'proctor_event_type',
        'trigger_parameters' => ['event_type' => 'multiple_faces'],
        'penalty_points' => 10,
    ]);

    $response->assertCreated()->assertJsonPath('data.penalty_name', 'Multiple Faces Detected');
});

it('denies creating a penalty rule when the actor lacks penalties.manage', function (): void {
    $viewer = $this->createUser($this->tenantA, password: 'ViewerPass1!');
    $this->grantPermissionsToUser($viewer, ['penalties.view']); // view only
    Sanctum::actingAs($viewer);

    $this->postJson('/api/v1/penalty-rules', [
        'penalty_name' => 'Hacked Rule',
        'penalty_type' => 'penalty',
        'trigger_condition' => 'proctor_event_type',
    ])->assertForbidden();
});

it('denies deleting a penalty rule when the actor lacks penalties.manage', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $rule = $this->createPenaltyRule($this->tenantA, (string) $admin->id);

    $viewer = $this->createUser($this->tenantA, password: 'ViewerPass1!');
    $this->grantPermissionsToUser($viewer, ['penalties.view']);
    Sanctum::actingAs($viewer);

    $this->deleteJson("/api/v1/penalty-rules/{$rule->penalty_rule_id}")
        ->assertForbidden();
});

it('denies viewing a penalty rule that belongs to another tenant', function (): void {
    $this->initializeTenantContext($this->tenantB);
    $adminB = $this->createUser($this->tenantB, password: 'AdminBPass1!');
    $ruleB = $this->createPenaltyRule($this->tenantB, (string) $adminB->id);

    $this->initializeTenantContext($this->tenantA);
    $actorA = $this->createUser($this->tenantA, password: 'ActorPass1!');
    $this->grantPermissionsToUser($actorA, ['penalties.view']);
    Sanctum::actingAs($actorA);

    $this->getJson("/api/v1/penalty-rules/{$ruleB->penalty_rule_id}")
        ->assertNotFound();
});

it('denies voiding a sanction when the actor lacks penalties.manage', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $rule = $this->createPenaltyRule($this->tenantA, (string) $admin->id);
    $exam = $this->createExam($this->tenantA, (string) $admin->id);
    $enrollment = $this->createEnrollment($this->tenantA, $exam->exam_id, (string) $admin->id);
    $session = $this->createExamSession(
        $this->tenantA,
        $exam->exam_id,
        $enrollment->enrollment_id,
        (string) $admin->id,
    );
    $sanction = $this->createPenaltySanction(
        $this->tenantA,
        (string) $session->session_id,
        (string) $admin->id,
        (string) $rule->penalty_rule_id,
    );

    $viewer = $this->createUser($this->tenantA, password: 'ViewerPass1!');
    $this->grantPermissionsToUser($viewer, ['penalties.view']);
    Sanctum::actingAs($viewer);

    $this->postJson("/api/v1/sanctions/{$sanction->sanction_id}/void", [
        'reason' => 'Trying to bypass authorization',
    ])->assertForbidden();
});