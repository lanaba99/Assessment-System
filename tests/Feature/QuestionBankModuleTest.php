<?php

declare(strict_types=1);

use App\Domains\QuestionBank\Models\Question;
use App\Domains\QuestionBank\Models\QuestionBank;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\QuestionBank\UsesQuestionBankSchema;

uses(UsesQuestionBankSchema::class);

beforeEach(function (): void {
    $this->bootQuestionBankSchema();
    $this->withoutTenancyIdentificationMiddleware();
    $this->initializeTenantContext($this->tenantA);
});

it('returns the recursive category tree', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['categories.manage']);
    Sanctum::actingAs($admin);

    $root = $this->createCategory($this->tenantA, 'Root');
    $child = $this->createCategory($this->tenantA, 'Child', (string) $root->category_id);

    $response = $this->getJson('/api/v1/categories/tree');

    $response
        ->assertOk()
        ->assertJsonPath('data.0.id', (string) $root->category_id)
        ->assertJsonPath('data.0.children.0.id', (string) $child->category_id);
});

it('prevents deleting a category that has subcategories or questions', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['categories.manage', 'questions.manage']);
    Sanctum::actingAs($admin);

    $parent = $this->createCategory($this->tenantA, 'Parent');
    $this->createCategory($this->tenantA, 'Child', (string) $parent->category_id);

    $this->deleteJson('/api/v1/categories/' . $parent->category_id)
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'category_not_empty')
        ->assertJsonPath('error.has_children', true);

    $leaf = $this->createCategory($this->tenantA, 'Leaf');

    $this->postJson('/api/v1/questions', [
        'category_id' => $leaf->category_id,
        'title' => 'Sample MCQ',
        'type' => 'mcq',
        'question_text' => 'What is 2 + 2?',
        'bloom_level' => 2,
        'choices' => [
            ['option_text' => '3', 'is_correct' => false],
            ['option_text' => '4', 'is_correct' => true],
        ],
    ])->assertCreated();

    $this->deleteJson('/api/v1/categories/' . $leaf->category_id)
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'category_not_empty')
        ->assertJsonPath('error.has_questions', true);
});

it('creates an mcq question with multiple choices', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['categories.manage', 'questions.manage']);
    Sanctum::actingAs($admin);

    $category = $this->createCategory($this->tenantA, 'Math');

    $response = $this->postJson('/api/v1/questions', [
        'category_id' => $category->category_id,
        'title' => 'Addition basics',
        'type' => 'mcq',
        'question_text' => 'What is 2 + 2?',
        'stem' => 'Choose the correct sum.',
        'bloom_level' => 2,
        'difficulty_level' => 1,
        'choices' => [
            ['option_text' => '3', 'is_correct' => false, 'option_sequence' => 1],
            ['option_text' => '4', 'is_correct' => true, 'option_sequence' => 2],
            ['option_text' => '5', 'is_correct' => false, 'option_sequence' => 3],
        ],
        'psychometrics' => [
            'p_value' => 0.75,
            'discrimination_index' => 0.42,
            'usage_count' => 3,
        ],
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.type', 'mcq')
        ->assertJsonPath('data.bloom_level', 2)
        ->assertJsonPath('data.usage_count', 3)
        ->assertJsonCount(3, 'data.choices')
        ->assertJsonPath('data.psychometrics.p_value', '0.7500')
        ->assertJsonPath('data.psychometrics.discrimination_index', '0.4200');

    $question = Question::query()
        ->where('tenant_id', $this->tenantA)
        ->where('question_title', 'Addition basics')
        ->first();

    expect($question)->not->toBeNull();
    expect($question->current_version_id)->not->toBeNull();
    expect($question->currentVersion?->options)->toHaveCount(3);
});

it('filters questions on the listing endpoint', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['categories.manage', 'questions.manage']);
    Sanctum::actingAs($admin);

    $categoryA = $this->createCategory($this->tenantA, 'Category A');
    $categoryB = $this->createCategory($this->tenantA, 'Category B');

    $this->postJson('/api/v1/questions', [
        'category_id' => $categoryA->category_id,
        'title' => 'Remember item',
        'type' => 'mcq',
        'question_text' => 'Pick one.',
        'bloom_level' => 1,
        'choices' => [
            ['option_text' => 'A', 'is_correct' => true],
            ['option_text' => 'B', 'is_correct' => false],
        ],
    ])->assertCreated();

    $this->postJson('/api/v1/questions', [
        'category_id' => $categoryB->category_id,
        'title' => 'Apply item',
        'type' => 'essay',
        'question_text' => 'Explain your approach.',
        'bloom_level' => 3,
    ])->assertCreated();

    $this->getJson('/api/v1/questions?category_id=' . $categoryA->category_id . '&bloom_level=1&type=mcq')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Remember item');

    $this->getJson('/api/v1/questions?type=essay')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Apply item');
});

it('deletes an empty leaf category', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['categories.manage']);
    Sanctum::actingAs($admin);

    $leaf = $this->createCategory($this->tenantA, 'Disposable');

    $this->deleteJson('/api/v1/categories/' . $leaf->category_id)
        ->assertNoContent();

    expect(QuestionBank::query()->whereKey($leaf->category_id)->exists())->toBeFalse();
});
