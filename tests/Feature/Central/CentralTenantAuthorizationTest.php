<?php

declare(strict_types=1);

use App\Domains\Central\Models\CentralAdminUser;
use App\Models\Tenant;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Stancl\Tenancy\Events\TenantCreated;
use Tests\Feature\Central\UsesCentralSchema;

uses(UsesCentralSchema::class);

beforeEach(function (): void {
    $this->bootCentralSchema();
    Event::fake([TenantCreated::class]);
});

/**
 * A central admin without is_super_admin and without the '*' wildcard —
 * CentralTenantPolicy::isSuperAdmin() must deny every action for this actor.
 */
function createLimitedCentralAdmin(): CentralAdminUser
{
    return CentralAdminUser::query()->forceCreate([
        'admin_user_id' => (string) Str::uuid(),
        'email' => 'limited-admin@central.test',
        'password_hash' => Hash::make('LimitedPass1!'),
        'first_name' => 'Limited',
        'last_name' => 'Admin',
        'admin_permissions' => [],
        'is_super_admin' => false,
        'status' => 'active',
    ]);
}

it('denies listing tenants for a non-super-admin central user', function (): void {
    $limited = createLimitedCentralAdmin();
    Sanctum::actingAs($limited, [], 'sanctum');

    $this->getJson('/api/v1/admin/tenants')
        ->assertForbidden();
});

it('denies creating a tenant for a non-super-admin central user', function (): void {
    $limited = createLimitedCentralAdmin();
    Sanctum::actingAs($limited, [], 'sanctum');

    $this->postJson('/api/v1/admin/tenants', [
        'organization_name' => 'Rogue Org',
        'primary_contact_email' => 'rogue@example.com',
        'domain' => 'rogue-org',
    ])->assertForbidden();
});

it('denies viewing a tenant for a non-super-admin central user', function (): void {
    $tenant = Tenant::query()->forceCreate([
        'id' => (string) Str::uuid(),
        'organization_name' => 'Existing Org',
        'primary_contact_email' => 'existing@example.com',
        'domain' => 'existing-org',
        'status' => 'active',
    ]);

    $limited = createLimitedCentralAdmin();
    Sanctum::actingAs($limited, [], 'sanctum');

    $this->getJson("/api/v1/admin/tenants/{$tenant->id}")
        ->assertForbidden();
});

it('denies access to admin tenant routes for an unauthenticated request', function (): void {
    $this->getJson('/api/v1/admin/tenants')
        ->assertUnauthorized();
});

it('returns 404 for a tenant that does not exist, even for a super admin', function (): void {
    $this->createCentralAdmin('AdminPass1!');
    $login = $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'superadmin@central.test',
        'password' => 'AdminPass1!',
    ])->assertOk();

    $token = $login->json('data.token');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/admin/tenants/' . (string) Str::uuid())
        ->assertNotFound();
});