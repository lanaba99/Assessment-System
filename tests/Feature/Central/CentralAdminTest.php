<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Support\Facades\Event;
use Stancl\Tenancy\Events\TenantCreated;
use Tests\Feature\Central\UsesCentralSchema;

uses(UsesCentralSchema::class);

beforeEach(function (): void {
    $this->bootCentralSchema();
    Event::fake([TenantCreated::class]);
});

it('authenticates a central admin and returns a bearer token', function (): void {
    $this->createCentralAdmin('ChangeMe123!');

    $response = $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'superadmin@central.test',
        'password' => 'ChangeMe123!',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.scope', 'central')
        ->assertJsonStructure(['data' => ['token', 'admin_user_id', 'email']]);
});

it('creates a tenant and dispatches the TenantCreated lifecycle event', function (): void {
    $this->createCentralAdmin();

    $login = $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'superadmin@central.test',
        'password' => 'ChangeMe123!',
    ])->assertOk();

    $token = $login->json('data.token');

    $response = $this->withToken($token)->postJson('/api/v1/admin/tenants', [
        'organization_name' => 'Beta Assessment Corp',
        'organization_type' => 'enterprise',
        'primary_contact_email' => 'contact@beta.test',
        'domain' => 'beta-corp',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.organization_name', 'Beta Assessment Corp')
        ->assertJsonPath('data.status', 'active');

    Event::assertDispatched(TenantCreated::class);

    expect(Tenant::query()->where('organization_name', 'Beta Assessment Corp')->exists())->toBeTrue();
});

it('lists tenants for an authenticated central admin', function (): void {
    $this->createCentralAdmin();

    Tenant::create([
        'organization_name' => 'Existing Tenant',
        'organization_type' => 'enterprise',
        'primary_contact_email' => 'existing@test.com',
        'status' => 'active',
    ]);

    $token = $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'superadmin@central.test',
        'password' => 'ChangeMe123!',
    ])->json('data.token');

    $this->withToken($token)
        ->getJson('/api/v1/admin/tenants')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
