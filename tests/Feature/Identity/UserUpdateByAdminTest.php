<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Tests\Feature\Identity\UsesIdentitySchema;

uses(UsesIdentitySchema::class);

beforeEach(function (): void {
    $this->bootIdentitySchema();
    $this->withoutTenancyIdentificationMiddleware();
    $this->initializeTenantContext($this->tenantA);
});

it('updates another user when the actor has users.update', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['users.update', 'users.view']);
    Sanctum::actingAs($admin);

    $target = $this->createUser($this->tenantA, password: 'TargetPass1!', overrides: [
        'first_name' => 'Old',
        'last_name' => 'Name',
    ]);

    $response = $this->patchJson("/api/v1/users/{$target->id}", [
        'first_name' => 'New',
        'last_name' => 'Name2',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.first_name', 'New')
        ->assertJsonPath('data.last_name', 'Name2');
});

it('denies update when the actor lacks users.update', function (): void {
    $user = $this->createUser($this->tenantA, password: 'UserPass1!');
    // no permissions granted
    Sanctum::actingAs($user);

    $target = $this->createUser($this->tenantA, password: 'TargetPass1!');

    $this->patchJson("/api/v1/users/{$target->id}", [
        'first_name' => 'Hacked',
    ])->assertForbidden();
});

it('denies updating a user that belongs to another tenant (404 before policy fires)', function (): void {
    $this->initializeTenantContext($this->tenantB);
    $targetB = $this->createUser($this->tenantB, password: 'TargetPass1!');

    $this->initializeTenantContext($this->tenantA);
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['users.update']);
    Sanctum::actingAs($admin);

    $this->patchJson("/api/v1/users/{$targetB->id}", [
        'first_name' => 'Hijacked',
    ])->assertNotFound();
});

it('cannot escalate privileges via admin-editable fields without the right test setup masking them', function (): void {
    // Sanity check: sensitive fields (status, is_active, user_type) ARE
    // admin-editable by design (ADMIN_EDITABLE_FIELDS) — this test just
    // documents that behavior so a future change to that allowlist is caught.
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['users.update']);
    Sanctum::actingAs($admin);

    $target = $this->createUser($this->tenantA, password: 'TargetPass1!');

    $this->patchJson("/api/v1/users/{$target->id}", [
        'is_active' => false,
    ])->assertOk()->assertJsonPath('data.is_active', false);
});