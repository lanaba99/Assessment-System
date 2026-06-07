<?php

declare(strict_types=1);

use App\Domains\Cohorts\Models\Cohort;
use App\Domains\Cohorts\Models\CohortMember;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Cohorts\UsesCohortSchema;

uses(UsesCohortSchema::class);

beforeEach(function (): void {
    $this->bootCohortSchema();
    $this->withoutTenancyIdentificationMiddleware();
    $this->initializeTenantContext($this->tenantA);
});

/*
|--------------------------------------------------------------------------
| Schema drift guard
|--------------------------------------------------------------------------
|
| If a production migration adds or removes a column on the cohorts or
| cohort_members tables and UsesCohortSchema is not updated, these tests
| will fail with a clear message pointing to the fix.
*/
it('has all required columns on the cohorts table', function (string $column): void {
    expect(Schema::hasColumn('cohorts', $column))->toBeTrue(
        "Column [{$column}] is missing from the cohorts table. "
        . 'Update UsesCohortSchema::migrateCohortTables() to stay in lockstep.'
    );
})->with([
    'cohort_id', 'tenant_id', 'created_by_user_id', 'parent_cohort_id',
    'cohort_name', 'cohort_code', 'cohort_type', 'cohort_description',
    'hierarchy_level', 'cohort_attributes', 'is_active',
    'created_at', 'updated_at',
]);

it('has all required columns on the cohort_members table', function (string $column): void {
    expect(Schema::hasColumn('cohort_members', $column))->toBeTrue(
        "Column [{$column}] is missing from cohort_members. "
        . 'Update UsesCohortSchema::migrateCohortTables() to stay in lockstep.'
    );
})->with([
    'member_id', 'cohort_id', 'user_id', 'tenant_id',
    'membership_role', 'added_at', 'removed_at', 'is_active_member',
]);

/*
|--------------------------------------------------------------------------
| Cohort creation
|--------------------------------------------------------------------------
*/
it('creates a cohort via the endpoint and returns the correct shape', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['cohorts.manage', 'cohorts.view']);
    Sanctum::actingAs($admin);

    $response = $this->postJson('/api/v1/cohorts', [
        'cohort_name' => 'Engineering Team',
        'cohort_code' => 'ENG-2026',
        'cohort_type' => 'team',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.cohort_name', 'Engineering Team')
        ->assertJsonPath('data.cohort_code', 'ENG-2026')
        ->assertJsonPath('data.cohort_type', 'team')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.hierarchy_level', 0);

    expect(Cohort::query()->where('cohort_code', 'ENG-2026')->exists())->toBeTrue();
});

it('stores tenant_id and created_by_user_id via forceCreate, not mass-assignment', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['cohorts.manage', 'cohorts.view']);
    Sanctum::actingAs($admin);

    $response = $this->postJson('/api/v1/cohorts', [
        'cohort_name' => 'Assignment Check',
        'cohort_code' => 'ASSIGN-CHECK-01',
        'cohort_type' => 'batch',
    ])->assertCreated();

    $cohortId = $response->json('data.id');
    $cohort = Cohort::query()->whereKey($cohortId)->firstOrFail();

    expect((string) $cohort->tenant_id)->toBe($this->tenantA);
    expect((string) $cohort->created_by_user_id)->toBe((string) $admin->id);
});

it('rejects cohort creation with an invalid cohort_type', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['cohorts.manage']);
    Sanctum::actingAs($admin);

    $this->postJson('/api/v1/cohorts', [
        'cohort_name' => 'Bad Type Cohort',
        'cohort_code' => 'BAD-001',
        'cohort_type' => 'invalid_type',
    ])->assertUnprocessable();
});

it('rejects cohort creation with a duplicate cohort_code', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['cohorts.manage']);
    Sanctum::actingAs($admin);

    $this->createCohort($this->tenantA, (string) $admin->id, ['cohort_code' => 'DUPE-001']);

    $this->postJson('/api/v1/cohorts', [
        'cohort_name' => 'Another Cohort',
        'cohort_code' => 'DUPE-001',
        'cohort_type' => 'team',
    ])->assertUnprocessable();
});

it('rejects cohort creation for a user without cohorts.manage permission', function (): void {
    $viewer = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($viewer, ['cohorts.view']);
    Sanctum::actingAs($viewer);

    $this->postJson('/api/v1/cohorts', [
        'cohort_name' => 'Forbidden Cohort',
        'cohort_code' => 'FORBIDDEN-001',
        'cohort_type' => 'team',
    ])->assertForbidden();
});

it('creates a child cohort and derives hierarchy_level from parent', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['cohorts.manage', 'cohorts.view']);
    Sanctum::actingAs($admin);

    $parent = $this->createCohort($this->tenantA, (string) $admin->id, [
        'cohort_code' => 'PARENT-001',
        'hierarchy_level' => 0,
    ]);

    $response = $this->postJson('/api/v1/cohorts', [
        'cohort_name' => 'Child Team',
        'cohort_code' => 'CHILD-001',
        'cohort_type' => 'team',
        'parent_cohort_id' => (string) $parent->cohort_id,
    ])->assertCreated();

    // Service derives hierarchy_level = parent.level + 1.
    $response->assertJsonPath('data.hierarchy_level', 1);
    $response->assertJsonPath('data.parent_cohort_id', (string) $parent->cohort_id);
});

/*
|--------------------------------------------------------------------------
| Read
|--------------------------------------------------------------------------
*/
it('returns a single cohort via the show endpoint', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['cohorts.view']);
    Sanctum::actingAs($admin);

    $cohort = $this->createCohort($this->tenantA, (string) $admin->id, ['cohort_name' => 'Readable Cohort']);

    $this->getJson('/api/v1/cohorts/' . $cohort->cohort_id)
        ->assertOk()
        ->assertJsonPath('data.id', (string) $cohort->cohort_id)
        ->assertJsonPath('data.cohort_name', 'Readable Cohort')
        ->assertJsonPath('data.is_active', true);
});

it('returns 404 for a cohort that does not exist', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['cohorts.view']);
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/cohorts/' . Str::uuid())
        ->assertNotFound()
        ->assertJsonPath('error.code', 'cohort_not_found');
});

it('lists all tenant cohorts via the index endpoint', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['cohorts.view']);
    Sanctum::actingAs($admin);

    $this->createCohort($this->tenantA, (string) $admin->id, ['cohort_code' => 'LIST-001']);
    $this->createCohort($this->tenantA, (string) $admin->id, ['cohort_code' => 'LIST-002']);

    $response = $this->getJson('/api/v1/cohorts')->assertOk();

    expect(count($response->json('data')))->toBeGreaterThanOrEqual(2);
});

it('rejects an unauthenticated read request with 401', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $cohort = $this->createCohort($this->tenantA, (string) $admin->id);

    // No Sanctum::actingAs — unauthenticated.
    $this->getJson('/api/v1/cohorts/' . $cohort->cohort_id)
        ->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| Update
|--------------------------------------------------------------------------
*/
it('updates cohort fields via PATCH and returns the updated cohort', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['cohorts.manage', 'cohorts.view']);
    Sanctum::actingAs($admin);

    $cohort = $this->createCohort($this->tenantA, (string) $admin->id);

    $this->patchJson('/api/v1/cohorts/' . $cohort->cohort_id, [
        'cohort_name' => 'Updated Name',
        'cohort_description' => 'A fresh description.',
    ])
        ->assertOk()
        ->assertJsonPath('data.cohort_name', 'Updated Name')
        ->assertJsonPath('data.cohort_description', 'A fresh description.')
        // Unpatched fields are preserved.
        ->assertJsonPath('data.is_active', true);
});

it('can deactivate a cohort via PATCH is_active', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['cohorts.manage', 'cohorts.view']);
    Sanctum::actingAs($admin);

    $cohort = $this->createCohort($this->tenantA, (string) $admin->id);

    $this->patchJson('/api/v1/cohorts/' . $cohort->cohort_id, ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);
});

it('returns 404 when patching a non-existent cohort', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['cohorts.manage']);
    Sanctum::actingAs($admin);

    $this->patchJson('/api/v1/cohorts/' . Str::uuid(), [
        'cohort_name' => 'Ghost',
    ])->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Delete
|--------------------------------------------------------------------------
*/
it('deletes an empty cohort and returns 204', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['cohorts.manage']);
    Sanctum::actingAs($admin);

    $cohort = $this->createCohort($this->tenantA, (string) $admin->id);

    $this->deleteJson('/api/v1/cohorts/' . $cohort->cohort_id)->assertNoContent();

    expect(Cohort::query()->whereKey($cohort->cohort_id)->exists())->toBeFalse();
});

it('rejects deleting a cohort that has active members (409 Conflict)', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $member = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['cohorts.manage']);
    Sanctum::actingAs($admin);

    $cohort = $this->createCohort($this->tenantA, (string) $admin->id);
    $this->createCohortMember($this->tenantA, (string) $cohort->cohort_id, (string) $member->id);

    $this->deleteJson('/api/v1/cohorts/' . $cohort->cohort_id)
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'cohort_not_empty');
});

it('returns 404 when deleting a non-existent cohort', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['cohorts.manage']);
    Sanctum::actingAs($admin);

    $this->deleteJson('/api/v1/cohorts/' . Str::uuid())->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Member management — add
|--------------------------------------------------------------------------
*/
it('adds a user to a cohort and returns the membership record', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $newMember = $this->createUser($this->tenantA, password: 'MemberPass1!');
    $this->grantPermissionsToUser($admin, ['cohorts.view', 'cohorts.members.manage']);
    Sanctum::actingAs($admin);

    $cohort = $this->createCohort($this->tenantA, (string) $admin->id);

    $response = $this->postJson('/api/v1/cohorts/' . $cohort->cohort_id . '/members', [
        'user_id' => (string) $newMember->id,
        'membership_role' => 'member',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.cohort_id', (string) $cohort->cohort_id)
        ->assertJsonPath('data.user_id', (string) $newMember->id)
        ->assertJsonPath('data.membership_role', 'member')
        ->assertJsonPath('data.is_active_member', true);

    expect(
        CohortMember::query()
            ->where('cohort_id', $cohort->cohort_id)
            ->where('user_id', $newMember->id)
            ->where('is_active_member', true)
            ->exists()
    )->toBeTrue();
});

it('rejects adding a user who is already an active member (409 Conflict)', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $existing = $this->createUser($this->tenantA, password: 'MemberPass1!');
    $this->grantPermissionsToUser($admin, ['cohorts.view', 'cohorts.members.manage']);
    Sanctum::actingAs($admin);

    $cohort = $this->createCohort($this->tenantA, (string) $admin->id);
    $this->createCohortMember($this->tenantA, (string) $cohort->cohort_id, (string) $existing->id);

    $this->postJson('/api/v1/cohorts/' . $cohort->cohort_id . '/members', [
        'user_id' => (string) $existing->id,
    ])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'duplicate_member');
});

it('rejects adding a member without cohorts.members.manage permission', function (): void {
    $viewer = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $target = $this->createUser($this->tenantA, password: 'TargetPass1!');
    $this->grantPermissionsToUser($viewer, ['cohorts.view']);
    Sanctum::actingAs($viewer);

    $cohort = $this->createCohort($this->tenantA, (string) $viewer->id);

    $this->postJson('/api/v1/cohorts/' . $cohort->cohort_id . '/members', [
        'user_id' => (string) $target->id,
    ])->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Member management — list
|--------------------------------------------------------------------------
*/
it('lists active members of a cohort', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $memberA = $this->createUser($this->tenantA);
    $memberB = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($admin, ['cohorts.view']);
    Sanctum::actingAs($admin);

    $cohort = $this->createCohort($this->tenantA, (string) $admin->id);
    $this->createCohortMember($this->tenantA, (string) $cohort->cohort_id, (string) $memberA->id);
    $this->createCohortMember($this->tenantA, (string) $cohort->cohort_id, (string) $memberB->id);

    $response = $this->getJson('/api/v1/cohorts/' . $cohort->cohort_id . '/members')
        ->assertOk();

    expect(count($response->json('data')))->toBe(2);
});

it('does not include soft-removed members in the listing', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $activeMember = $this->createUser($this->tenantA);
    $removedMember = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($admin, ['cohorts.view']);
    Sanctum::actingAs($admin);

    $cohort = $this->createCohort($this->tenantA, (string) $admin->id);
    $this->createCohortMember($this->tenantA, (string) $cohort->cohort_id, (string) $activeMember->id);
    $this->createCohortMember($this->tenantA, (string) $cohort->cohort_id, (string) $removedMember->id, [
        'is_active_member' => false,
        'removed_at' => now(),
    ]);

    $response = $this->getJson('/api/v1/cohorts/' . $cohort->cohort_id . '/members')
        ->assertOk();

    expect(count($response->json('data')))->toBe(1);
    expect($response->json('data.0.user_id'))->toBe((string) $activeMember->id);
});

/*
|--------------------------------------------------------------------------
| Member management — remove
|--------------------------------------------------------------------------
*/
it('soft-removes a member and returns 204', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $target = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($admin, ['cohorts.view', 'cohorts.members.manage']);
    Sanctum::actingAs($admin);

    $cohort = $this->createCohort($this->tenantA, (string) $admin->id);
    $this->createCohortMember($this->tenantA, (string) $cohort->cohort_id, (string) $target->id);

    $this->deleteJson('/api/v1/cohorts/' . $cohort->cohort_id . '/members/' . $target->id)
        ->assertNoContent();

    // The membership row must remain in the DB (audit trail) but be inactive.
    $membership = CohortMember::query()
        ->where('cohort_id', $cohort->cohort_id)
        ->where('user_id', $target->id)
        ->firstOrFail();

    expect($membership->is_active_member)->toBeFalse();
    expect($membership->removed_at)->not->toBeNull();
});

it('returns 404 when removing a user who is not a member', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $nonMember = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($admin, ['cohorts.view', 'cohorts.members.manage']);
    Sanctum::actingAs($admin);

    $cohort = $this->createCohort($this->tenantA, (string) $admin->id);

    $this->deleteJson('/api/v1/cohorts/' . $cohort->cohort_id . '/members/' . $nonMember->id)
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Multi-tenancy isolation
|--------------------------------------------------------------------------
*/
it('hides a tenantB cohort from a tenantA user (404 from repository scope)', function (): void {
    // Create a cohort under tenantB's context.
    $this->initializeTenantContext($this->tenantB);
    $ownerB = $this->createUser($this->tenantB, password: 'OwnerPass1!');
    $cohortB = $this->createCohort($this->tenantB, (string) $ownerB->id);

    // Switch to tenantA — the repository filters by tenant_id.
    $this->initializeTenantContext($this->tenantA);
    $actorA = $this->createUser($this->tenantA, password: 'ActorPass1!');
    $this->grantPermissionsToUser($actorA, ['cohorts.view', 'cohorts.manage']);
    Sanctum::actingAs($actorA);

    $this->getJson('/api/v1/cohorts/' . $cohortB->cohort_id)
        ->assertNotFound();
});

it('prevents tenantA from updating a tenantB cohort (404 before policy fires)', function (): void {
    $this->initializeTenantContext($this->tenantB);
    $ownerB = $this->createUser($this->tenantB, password: 'OwnerPass1!');
    $cohortB = $this->createCohort($this->tenantB, (string) $ownerB->id);

    $this->initializeTenantContext($this->tenantA);
    $actorA = $this->createUser($this->tenantA, password: 'ActorPass1!');
    $this->grantPermissionsToUser($actorA, ['cohorts.manage']);
    Sanctum::actingAs($actorA);

    $this->patchJson('/api/v1/cohorts/' . $cohortB->cohort_id, [
        'cohort_name' => 'Hijacked Name',
    ])->assertNotFound();
});

it('prevents tenantA from adding a member to a tenantB cohort (404 before policy fires)', function (): void {
    $this->initializeTenantContext($this->tenantB);
    $ownerB = $this->createUser($this->tenantB, password: 'OwnerPass1!');
    $cohortB = $this->createCohort($this->tenantB, (string) $ownerB->id);

    $this->initializeTenantContext($this->tenantA);
    $actorA = $this->createUser($this->tenantA, password: 'ActorPass1!');
    $victimA = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($actorA, ['cohorts.members.manage']);
    Sanctum::actingAs($actorA);

    $this->postJson('/api/v1/cohorts/' . $cohortB->cohort_id . '/members', [
        'user_id' => (string) $victimA->id,
    ])->assertNotFound();
});

it('prevents tenantA from deleting a tenantB cohort (404 before policy fires)', function (): void {
    $this->initializeTenantContext($this->tenantB);
    $ownerB = $this->createUser($this->tenantB, password: 'OwnerPass1!');
    $cohortB = $this->createCohort($this->tenantB, (string) $ownerB->id);

    $this->initializeTenantContext($this->tenantA);
    $actorA = $this->createUser($this->tenantA, password: 'ActorPass1!');
    $this->grantPermissionsToUser($actorA, ['cohorts.manage']);
    Sanctum::actingAs($actorA);

    $this->deleteJson('/api/v1/cohorts/' . $cohortB->cohort_id)
        ->assertNotFound();
});

it('index only returns cohorts belonging to the current tenant', function (): void {
    // Create cohorts in both tenants.
    $this->initializeTenantContext($this->tenantA);
    $adminA = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($adminA, ['cohorts.view']);
    $this->createCohort($this->tenantA, (string) $adminA->id, ['cohort_code' => 'TENANT-A-COH']);

    $this->initializeTenantContext($this->tenantB);
    $adminB = $this->createUser($this->tenantB, password: 'AdminPass1!');
    $this->createCohort($this->tenantB, (string) $adminB->id, ['cohort_code' => 'TENANT-B-COH']);

    // Act as tenantA.
    $this->initializeTenantContext($this->tenantA);
    Sanctum::actingAs($adminA);

    $response = $this->getJson('/api/v1/cohorts')->assertOk();

    $codes = collect($response->json('data'))->pluck('cohort_code')->all();

    expect($codes)->toContain('TENANT-A-COH');
    expect($codes)->not->toContain('TENANT-B-COH');
});
