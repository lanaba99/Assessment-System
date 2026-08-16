<?php

declare(strict_types=1);

use App\Models\Tenant;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Identity\UsesIdentitySchema;

uses(UsesIdentitySchema::class);

beforeEach(function (): void {
    $this->bootIdentitySchema();
    $this->withoutTenancyIdentificationMiddleware();
    $this->initializeTenantContext($this->tenantA);
});

it('returns the current tenant settings for an authorized admin', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['tenant.manage']);
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/tenant/settings')
        ->assertOk()
        ->assertJsonStructure(['data' => [
            'id', 'organization_name', 'organization_type',
            'primary_contact_email', 'primary_contact_phone',
        ]])
        ->assertJsonPath('data.id', $this->tenantA);
});

it('updates the organization name and contact info', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['tenant.manage']);
    Sanctum::actingAs($admin);

    $this->patchJson('/api/v1/tenant/settings', [
        'organization_name' => 'Updated Org Name',
        'primary_contact_phone' => '+1-555-0100',
    ])
        ->assertOk()
        ->assertJsonPath('data.organization_name', 'Updated Org Name')
        ->assertJsonPath('data.primary_contact_phone', '+1-555-0100');

    $fresh = Tenant::query()->find($this->tenantA);
    expect($fresh->organization_name)->toBe('Updated Org Name');
});

it('returns 401 for an unauthenticated request', function (): void {
    $this->getJson('/api/v1/tenant/settings')->assertUnauthorized();
});

it('returns 403 for an authenticated actor without tenant.manage', function (): void {
    $user = $this->createUser($this->tenantA, password: 'UserPass1!');
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/tenant/settings')->assertForbidden();
});

it('rejects an invalid email on update with 422', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['tenant.manage']);
    Sanctum::actingAs($admin);

    $this->patchJson('/api/v1/tenant/settings', [
        'primary_contact_email' => 'not-an-email',
    ])->assertUnprocessable();
});

it('never lets a tenant-B admin see or change tenant-A settings', function (): void {
    $adminB = $this->createUser($this->tenantB, password: 'AdminPass1!');
    $this->grantPermissionsToUser($adminB, ['tenant.manage']);

    $this->initializeTenantContext($this->tenantB);
    Sanctum::actingAs($adminB);

    $response = $this->getJson('/api/v1/tenant/settings')->assertOk();

    // The endpoint has no {tenantId} param — it can only ever resolve to
    // whichever tenant initialized the request. Proving isolation means
    // proving it resolved tenant B, never tenant A.
    expect($response->json('data.id'))->toBe($this->tenantB)
        ->and($response->json('data.id'))->not->toBe($this->tenantA);
});