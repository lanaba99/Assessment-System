<?php

declare(strict_types=1);

use App\Domains\Identity\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Identity\UsesIdentitySchema;

uses(UsesIdentitySchema::class);

beforeEach(function (): void {
    $this->bootIdentitySchema();
    $this->withoutTenancyIdentificationMiddleware();
    $this->initializeTenantContext($this->tenantA);
    $this->createSecurityPolicy($this->tenantA, [
        'password_min_length' => 8,
        'password_require_uppercase' => true,
        'password_require_lowercase' => true,
        'password_require_numbers' => true,
        'password_require_special_chars' => true,
    ]);
});

it('invites a user and returns a pending account with invite token', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['users.create']);
    Sanctum::actingAs($admin);

    $response = $this->postJson('/api/v1/users/invite', [
        'email' => 'invited@example.test',
        'first_name' => 'Invited',
        'last_name' => 'User',
        'user_type' => 'examinee',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.tenant_id', $this->tenantA)
        ->assertJsonStructure(['data' => ['user_id', 'invite_token']]);

    $invited = User::query()
        ->where('tenant_id', $this->tenantA)
        ->where('email', 'invited@example.test')
        ->first();

    expect($invited)->not->toBeNull();
    expect($invited->status)->toBe('pending');
    expect((bool) $invited->is_active)->toBeFalse();
});

it('accepts an invite, activates the user, and returns an api token', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['users.create']);
    Sanctum::actingAs($admin);

    $inviteResponse = $this->postJson('/api/v1/users/invite', [
        'email' => 'newhire@example.test',
        'first_name' => 'New',
        'last_name' => 'Hire',
        'user_type' => 'examinee',
    ])->assertCreated();

    $inviteToken = (string) $inviteResponse->json('data.invite_token');

    $acceptResponse = $this->postJson('/api/v1/auth/accept-invite', [
        'email' => 'newhire@example.test',
        'token' => $inviteToken,
        'password' => 'SecurePass1!',
        'password_confirmation' => 'SecurePass1!',
    ]);

    $acceptResponse
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.tenant_id', $this->tenantA)
        ->assertJsonStructure(['data' => ['user_id', 'token']]);

    $activated = User::query()
        ->where('tenant_id', $this->tenantA)
        ->where('email', 'newhire@example.test')
        ->first();

    expect($activated)->not->toBeNull();
    expect($activated->status)->toBe('active');
    expect((bool) $activated->is_active)->toBeTrue();
    expect($activated->activated_at)->not->toBeNull();
});

it('returns tenant-aware system status for health checks', function (): void {
    $response = $this->getJson('/api/v1/system/status');

    $response
        ->assertOk()
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonPath('data.tenant_id', $this->tenantA)
        ->assertJsonPath('data.database', 'connected')
        ->assertJsonStructure(['data' => ['timestamp', 'environment']]);
});

it('updates the active security policy for authorized users', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['security_policies.view', 'security_policies.update']);
    Sanctum::actingAs($admin);

    $response = $this->patchJson('/api/v1/security/policies', [
        'mfa_enabled' => true,
        'password_min_length' => 10,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.mfa_enabled', true)
        ->assertJsonPath('data.password_min_length', 10);
});
