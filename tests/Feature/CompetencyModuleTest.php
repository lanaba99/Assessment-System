<?php

declare(strict_types=1);

use App\Domains\Competency\Models\Competency;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Competency\UsesCompetencySchema;

uses(UsesCompetencySchema::class);

beforeEach(function (): void {
    $this->bootCompetencySchema();
    $this->withoutTenancyIdentificationMiddleware();
    $this->initializeTenantContext($this->tenantA);
});

it('returns the recursive competency tree', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['competencies.manage']);
    Sanctum::actingAs($admin);

    $root = $this->createCompetency($this->tenantA, (string) $admin->id, 'Root');
    $child = $this->createCompetency($this->tenantA, (string) $admin->id, 'Child', (string) $root->competency_id);

    $this->getJson('/api/v1/competencies/tree')
        ->assertOk()
        ->assertJsonPath('data.0.id', (string) $root->competency_id)
        ->assertJsonPath('data.0.children.0.id', (string) $child->competency_id);
});

it('creates a root competency via the endpoint', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['competencies.manage']);
    Sanctum::actingAs($admin);

    $this->postJson('/api/v1/competencies', [
        'name' => 'Communication',
        'description' => 'Core communication skills.',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Communication')
        ->assertJsonPath('data.parent_id', null)
        ->assertJsonPath('data.hierarchy_level', 0);

    expect(Competency::query()->where('competency_name', 'Communication')->exists())->toBeTrue();
});

it('creates a nested competency under a parent', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['competencies.manage']);
    Sanctum::actingAs($admin);

    $root = $this->createCompetency($this->tenantA, (string) $admin->id, 'Engineering');

    $this->postJson('/api/v1/competencies', [
        'name' => 'Backend Development',
        'parent_id' => (string) $root->competency_id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.parent_id', (string) $root->competency_id)
        ->assertJsonPath('data.hierarchy_level', 1);
});

it('prevents deleting a competency that has sub-competencies', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['competencies.manage']);
    Sanctum::actingAs($admin);

    $parent = $this->createCompetency($this->tenantA, (string) $admin->id, 'Parent');
    $this->createCompetency($this->tenantA, (string) $admin->id, 'Child', (string) $parent->competency_id);

    $this->deleteJson('/api/v1/competencies/' . $parent->competency_id)
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'competency_not_empty')
        ->assertJsonPath('error.has_children', true);
});

it('moves a competency under a new parent', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['competencies.manage']);
    Sanctum::actingAs($admin);

    $alpha = $this->createCompetency($this->tenantA, (string) $admin->id, 'Alpha');
    $beta = $this->createCompetency($this->tenantA, (string) $admin->id, 'Beta');

    $this->patchJson('/api/v1/competencies/' . $alpha->competency_id . '/move', [
        'parent_id' => (string) $beta->competency_id,
    ])
        ->assertOk()
        ->assertJsonPath('data.parent_id', (string) $beta->competency_id)
        ->assertJsonPath('data.hierarchy_level', 1);
});

it('rejects moving a competency under one of its own descendants', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['competencies.manage']);
    Sanctum::actingAs($admin);

    $root = $this->createCompetency($this->tenantA, (string) $admin->id, 'Root');
    $child = $this->createCompetency($this->tenantA, (string) $admin->id, 'Child', (string) $root->competency_id);

    $this->patchJson('/api/v1/competencies/' . $root->competency_id . '/move', [
        'parent_id' => (string) $child->competency_id,
    ])->assertStatus(422);
});

it('deletes an empty leaf competency', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['competencies.manage']);
    Sanctum::actingAs($admin);

    $leaf = $this->createCompetency($this->tenantA, (string) $admin->id, 'Disposable');

    $this->deleteJson('/api/v1/competencies/' . $leaf->competency_id)
        ->assertNoContent();

    expect(Competency::query()->whereKey($leaf->competency_id)->exists())->toBeFalse();
});
