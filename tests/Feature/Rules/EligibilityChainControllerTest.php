<?php

declare(strict_types=1);

use App\Domains\Rules\Models\EligibilityChain;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Rules\UsesRulesSchema;

uses(UsesRulesSchema::class);

beforeEach(function (): void {
    $this->bootRulesSchema();
    $this->withoutTenancyIdentificationMiddleware();
    $this->initializeTenantContext($this->tenantA);
});

it('creates an eligibility chain when the actor has eligibility.manage', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['eligibility.manage', 'eligibility.view']);
    Sanctum::actingAs($admin);

    $exam = $this->createExam($this->tenantA, (string) $admin->id);
    $prereq = $this->createExam($this->tenantA, (string) $admin->id);

    $response = $this->postJson('/api/v1/eligibility-chains', [
        'exam_id' => $exam->exam_id,
        'chain_step_number' => 1,
        'prerequisite_exam_id' => $prereq->exam_id,
        'condition_type' => 'prerequisite_exam',
        'logical_operator' => 'AND',
        'min_score_required' => 60,
    ]);

    $response->assertCreated()->assertJsonPath('data.exam_id', $exam->exam_id);
});

it('denies creation when the actor lacks eligibility.manage', function (): void {
    $user = $this->createUser($this->tenantA, password: 'UserPass1!');
    // no permissions granted
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/eligibility-chains', [
        'exam_id' => (string) Str::uuid(),
        'chain_step_number' => 1,
        'condition_type' => 'prerequisite_exam',
    ])->assertForbidden();
});

it('denies viewing a chain that belongs to another tenant', function (): void {
    // Create the chain under tenantB's context first.
    $this->initializeTenantContext($this->tenantB);
    $ownerB = $this->createUser($this->tenantB, password: 'OwnerPass1!');
    $examB = $this->createExam($this->tenantB, (string) $ownerB->id);
    $prereqB = $this->createExam($this->tenantB, (string) $ownerB->id);
    $chainB = $this->createEligibilityChainStep(
        tenantId: $this->tenantB,
        examId: $examB->exam_id,
        createdByUserId: (string) $ownerB->id,
        stepNumber: 1,
        prerequisiteExamId: $prereqB->exam_id,
    );

    // Switch to tenantA — the repository filters by tenant_id.
    $this->initializeTenantContext($this->tenantA);
    $actorA = $this->createUser($this->tenantA, password: 'ActorPass1!');
    $this->grantPermissionsToUser($actorA, ['eligibility.view']);
    Sanctum::actingAs($actorA);

    $this->getJson("/api/v1/eligibility-chains/{$chainB->chain_id}")
        ->assertNotFound();
});

it('denies update when the actor lacks eligibility.manage, proving controller-level authorize() is wired', function (): void {
    $viewer = $this->createUser($this->tenantA, password: 'ViewerPass1!');
    $this->grantPermissionsToUser($viewer, ['eligibility.view']); // view only, not manage
    Sanctum::actingAs($viewer);

    $exam = $this->createExam($this->tenantA, (string) $viewer->id);
    $prereq = $this->createExam($this->tenantA, (string) $viewer->id);
    $chain = $this->createEligibilityChainStep(
        tenantId: $this->tenantA,
        examId: $exam->exam_id,
        createdByUserId: (string) $viewer->id,
        stepNumber: 1,
        prerequisiteExamId: $prereq->exam_id,
    );

    $this->patchJson("/api/v1/eligibility-chains/{$chain->chain_id}", [
        'min_score_required' => 80,
    ])->assertForbidden();
});

it('deletes a chain when the actor has eligibility.manage', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['eligibility.manage']);
    Sanctum::actingAs($admin);

    $exam = $this->createExam($this->tenantA, (string) $admin->id);
    $prereq = $this->createExam($this->tenantA, (string) $admin->id);
    $chain = $this->createEligibilityChainStep(
        tenantId: $this->tenantA,
        examId: $exam->exam_id,
        createdByUserId: (string) $admin->id,
        stepNumber: 1,
        prerequisiteExamId: $prereq->exam_id,
    );

    $this->deleteJson("/api/v1/eligibility-chains/{$chain->chain_id}")
        ->assertNoContent();

    expect(EligibilityChain::query()->whereKey($chain->chain_id)->exists())->toBeFalse();
});