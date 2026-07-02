<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Tests\Feature\QuestionBank\UsesQuestionBankSchema;

uses(UsesQuestionBankSchema::class);

beforeEach(function (): void {
    $this->bootQuestionBankSchema();
    $this->withoutTenancyIdentificationMiddleware();
    $this->initializeTenantContext($this->tenantA);
});

it('denies creating a category when the actor lacks categories.manage', function (): void {
    $user = $this->createUser($this->tenantA, password: 'UserPass1!');
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/categories', [
        'name' => 'Unauthorized Category',
    ])->assertForbidden();
});

it('denies deleting a category when the actor lacks categories.manage', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $category = $this->createCategory($this->tenantA, 'Leaf');

    $viewer = $this->createUser($this->tenantA, password: 'ViewerPass1!');
    Sanctum::actingAs($viewer);

    $this->deleteJson("/api/v1/categories/{$category->category_id}")
        ->assertForbidden();
});

it('denies creating a question when the actor lacks questions.manage', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['categories.manage']);
    $category = $this->createCategory($this->tenantA, 'Category');

    $viewer = $this->createUser($this->tenantA, password: 'ViewerPass1!');
    // categories.manage only, no questions.manage
    Sanctum::actingAs($viewer);

    $this->postJson('/api/v1/questions', [
        'category_id' => $category->category_id,
        'title' => 'Unauthorized MCQ',
        'type' => 'mcq',
        'question_text' => 'What is 2 + 2?',
        'bloom_level' => 2,
        'choices' => [
            ['option_text' => '3', 'is_correct' => false],
            ['option_text' => '4', 'is_correct' => true],
        ],
    ])->assertForbidden();
});

it('denies viewing a question when the actor lacks questions.manage', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['categories.manage', 'questions.manage']);
    $category = $this->createCategory($this->tenantA, 'Category');
    Sanctum::actingAs($admin);

    $created = $this->postJson('/api/v1/questions', [
        'category_id' => $category->category_id,
        'title' => 'Sample MCQ',
        'type' => 'mcq',
        'question_text' => 'What is 2 + 2?',
        'bloom_level' => 2,
        'choices' => [
            ['option_text' => '3', 'is_correct' => false],
            ['option_text' => '4', 'is_correct' => true],
        ],
    ])->assertCreated();
    $questionId = $created->json('data.id');

    $viewer = $this->createUser($this->tenantA, password: 'ViewerPass1!');
    // no permissions granted
    Sanctum::actingAs($viewer);

    $this->getJson("/api/v1/questions/{$questionId}")
        ->assertForbidden();
});

it('denies listing questions for an unauthenticated request', function (): void {
    $this->getJson('/api/v1/questions')
        ->assertUnauthorized();
});

it('denies viewing a category tree that belongs to another tenant (empty tree, not leaked)', function (): void {
    $this->initializeTenantContext($this->tenantB);
    $adminB = $this->createUser($this->tenantB, password: 'AdminBPass1!');
    $this->createCategory($this->tenantB, 'TenantB Root');

    $this->initializeTenantContext($this->tenantA);
    $adminA = $this->createUser($this->tenantA, password: 'AdminAPass1!');
    $this->grantPermissionsToUser($adminA, ['categories.manage']);
    Sanctum::actingAs($adminA);

    $response = $this->getJson('/api/v1/categories/tree')->assertOk();

    expect($response->json('data'))->toBeEmpty();
});