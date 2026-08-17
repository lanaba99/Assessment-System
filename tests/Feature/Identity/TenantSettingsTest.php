<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Identity\UsesIdentitySchema;

uses(UsesIdentitySchema::class);

beforeEach(function (): void {
    $this->bootIdentitySchema();
    $this->withoutTenancyIdentificationMiddleware();
    $this->initializeTenantContext($this->tenantA);
});
/**
 * IMPORTANT: this inserts via the raw query builder, not
 * Tenant::query()->create()/forceCreate(). Creating a Tenant through
 * Eloquent fires the model's `created` event, which stancl/tenancy hooks
 * to provision a REAL per-tenant database (it will try to open an actual
 * MySQL connection and fail in this sqlite-only test environment — this
 * is exactly what happened on the first attempt). A raw insert never
 * fires Eloquent events, so no provisioning side effect occurs. Reads
 * afterward go through the Tenant model normally (retrieval doesn't fire
 * creation events, so that's safe).
 */
function bindRealTenant(string $tenantId, array $attributes = []): Tenant
{
    if (! Schema::hasTable('tenants')) {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('organization_name');
            $table->string('organization_type')->nullable();
            $table->string('primary_contact_email');
            $table->string('primary_contact_phone')->nullable();
            $table->json('deployment_config')->nullable();
            $table->string('deployment_mode')->nullable();
            $table->string('data_residency_location')->nullable();
            $table->unsignedInteger('max_concurrent_users')->nullable();
            $table->unsignedInteger('max_storage_quota_mb')->nullable();
            $table->json('feature_flags')->nullable();
            $table->string('status')->default('active');
            $table->json('security_policies')->nullable();
            $table->dateTime('contract_start_date')->nullable();
            $table->dateTime('contract_end_date')->nullable();
            $table->timestamps();
            $table->timestamp('suspended_at')->nullable();
            $table->json('data')->nullable();
        });
    }

    $existing = DB::table('tenants')->where('id', $tenantId)->exists();

    if (! $existing) {
        DB::table('tenants')->insert(array_merge([
            'id' => $tenantId,
            'organization_name' => 'Test Org',
            'organization_type' => 'education',
            'primary_contact_email' => 'org-' . $tenantId . '@example.test',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    } elseif ($attributes !== []) {
        DB::table('tenants')->where('id', $tenantId)->update(array_merge($attributes, [
            'updated_at' => now(),
        ]));
    }

    $tenant = Tenant::query()->find($tenantId);
    app()->instance(Tenant::class, $tenant);

    return $tenant;
}

it('returns the current tenant settings for an authorized admin', function (): void {
    bindRealTenant($this->tenantA, ['organization_name' => 'Alpha Org']);

    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['tenant.manage']);
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/tenant/settings')
        ->assertOk()
        ->assertJsonStructure(['data' => [
            'id', 'organization_name', 'organization_type',
            'primary_contact_email', 'primary_contact_phone',
        ]])
        ->assertJsonPath('data.id', $this->tenantA)
        ->assertJsonPath('data.organization_name', 'Alpha Org');
});

it('updates the organization name and contact info', function (): void {
    bindRealTenant($this->tenantA);

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
    bindRealTenant($this->tenantA);

    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['tenant.manage']);
    Sanctum::actingAs($admin);

    $this->patchJson('/api/v1/tenant/settings', [
        'primary_contact_email' => 'not-an-email',
    ])->assertUnprocessable();
});

it('never lets a tenant-B admin see or change tenant-A settings', function (): void {
    bindRealTenant($this->tenantA, ['organization_name' => 'Tenant A Org']);

    $adminB = $this->createUser($this->tenantB, password: 'AdminPass1!');
    $this->grantPermissionsToUser($adminB, ['tenant.manage']);

    $this->initializeTenantContext($this->tenantB);
    bindRealTenant($this->tenantB, ['organization_name' => 'Tenant B Org']);
    Sanctum::actingAs($adminB);

    $response = $this->getJson('/api/v1/tenant/settings')->assertOk();

    // The endpoint has no {tenantId} param — it can only ever resolve to
    // whichever tenant initialized the request. Proving isolation means
    // proving it resolved tenant B's data, never tenant A's.
    expect($response->json('data.id'))->toBe($this->tenantB)
        ->and($response->json('data.organization_name'))->toBe('Tenant B Org');
});