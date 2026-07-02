<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Tests\Feature\Competency\UsesCompetencySchema;

uses(UsesCompetencySchema::class);

beforeEach(function (): void {
    $this->bootCompetencySchema();
    $this->withoutTenancyIdentificationMiddleware();
    $this->initializeTenantContext($this->tenantA);
});

it('denies creating a competency when the actor lacks competencies.manage', function (): void {
    $user = $this->createUser($this->tenantA, password: 'UserPass1!');
    // no permissions granted
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/competencies', [
        'name' => 'Unauthorized Competency',
    ])->assertForbidden();
});

it('denies moving a competency when the actor lacks competencies.manage', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $root = $this->createCompetency($this->tenantA, (string) $admin->id, 'Root');
    $newParent = $this->createCompetency($this->tenantA, (string) $admin->id, 'NewParent');

    $viewer = $this->createUser($this->tenantA, password: 'ViewerPass1!');
    // no permissions granted at all — competencies has no separate view permission
    Sanctum::actingAs($viewer);

    $this->patchJson("/api/v1/competencies/{$root->competency_id}/move", [
        'parent_id' => $newParent->competency_id,
    ])->assertForbidden();
});

it('denies deleting a competency when the actor lacks competencies.manage', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $leaf = $this->createCompetency($this->tenantA, (string) $admin->id, 'Leaf');

    $viewer = $this->createUser($this->tenantA, password: 'ViewerPass1!');
    Sanctum::actingAs($viewer);

    $this->deleteJson("/api/v1/competencies/{$leaf->competency_id}")
        ->assertForbidden();
});

it('denies moving a competency that belongs to another tenant (403 via FormRequest authorize())', function (): void {
    $this->initializeTenantContext($this->tenantB);
    $adminB = $this->createUser($this->tenantB, password: 'AdminBPass1!');
    $competencyB = $this->createCompetency($this->tenantB, (string) $adminB->id, 'TenantB Root');

    $this->initializeTenantContext($this->tenantA);
    $adminA = $this->createUser($this->tenantA, password: 'AdminAPass1!');
    $newParentA = $this->createCompetency($this->tenantA, (string) $adminA->id, 'TenantA Parent');
    $this->grantPermissionsToUser($adminA, ['competencies.manage']);
    Sanctum::actingAs($adminA);

    // MoveCompetencyRequest::authorize() looks the competency up scoped by
    // tenant; a cross-tenant id resolves to null, so authorize() returns
    // false and Laravel raises a 403 (AuthorizationException) — NOT a 404,
    // because the request never reaches the controller's own not-found branch.
    $this->patchJson("/api/v1/competencies/{$competencyB->competency_id}/move", [
        'parent_id' => $newParentA->competency_id,
    ])->assertForbidden();
});