<?php

declare(strict_types=1);

use App\Domains\QuestionBank\Models\Question;
use App\Domains\QuestionBank\Models\QuestionVersion;
use App\Http\Resources\QuestionCandidateResource;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\QuestionBank\UsesQuestionBankSchema;

uses(UsesQuestionBankSchema::class);

beforeEach(function (): void {
    $this->bootQuestionBankSchema();
    $this->withoutTenancyIdentificationMiddleware();
    $this->initializeTenantContext($this->tenantA);
});

/*
|--------------------------------------------------------------------------
| Schema-drift guard
|--------------------------------------------------------------------------
|
| The QueryException we hit in tinker ("Unknown column categories.deleted_at")
| came from a half-applied migration. These tests assert the test schema
| actually carries the columns the models depend on. If a migration is
| interrupted, or a new alter migration is forgotten in UsesQuestionBankSchema,
| this fails immediately with a pointer to the fix — not a cryptic 500 later.
*/
it('keeps the soft-delete column present on every soft-deletable table', function (string $table): void {
    expect(Schema::hasColumn($table, 'deleted_at'))->toBeTrue(
        "Table [{$table}] is missing `deleted_at`. A model on this table uses the SoftDeletes "
        . 'trait; register the soft-deletes migration in '
        . 'UsesQuestionBankSchema::migrateQuestionBankTables().'
    );
})->with(['categories', 'questions', 'question_versions']);

it('keeps the columns true versioning depends on', function (): void {
    expect(Schema::hasColumn('questions', 'current_version_id'))->toBeTrue();
    expect(Schema::hasColumn('question_versions', 'ver_num'))->toBeTrue();
    expect(Schema::hasColumn('question_versions', 'correct_answer_json'))->toBeTrue();
    expect(Schema::hasColumn('question_versions', 'content_hash'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Priority #3 — every type persists a gradable answer key
|--------------------------------------------------------------------------
*/
it('creates a true/false question with two graded options and an answer key', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['categories.manage', 'questions.manage']);
    Sanctum::actingAs($admin);
    $category = $this->createCategory($this->tenantA, 'General');

    $response = $this->postJson('/api/v1/questions', [
        'category_id' => $category->category_id,
        'title' => 'Sky colour',
        'type' => 'true_false',
        'question_text' => 'The sky is blue.',
        'bloom_level' => 1,
        'correct_answer' => true,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.type', 'true_false')
        ->assertJsonCount(2, 'data.choices')
        ->assertJsonPath('data.correct_answer.value', true);

    $version = QuestionVersion::query()
        ->where('question_id', $response->json('data.id'))
        ->firstOrFail();

    expect($version->correct_answer_json)->toBe(['value' => true]);
    expect($version->options->firstWhere('option_text', 'True')->is_correct)->toBeTrue();
    expect($version->options->firstWhere('option_text', 'False')->is_correct)->toBeFalse();
});

it('creates a short-answer question that stores accepted answers', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['categories.manage', 'questions.manage']);
    Sanctum::actingAs($admin);
    $category = $this->createCategory($this->tenantA, 'General');

    $this->postJson('/api/v1/questions', [
        'category_id' => $category->category_id,
        'title' => 'Chemical formula',
        'type' => 'short_answer',
        'question_text' => 'Formula for water?',
        'bloom_level' => 1,
        'accepted_answers' => ['H2O', 'water'],
        'match_mode' => 'case_insensitive',
    ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'short_answer')
        ->assertJsonCount(0, 'data.choices')
        ->assertJsonPath('data.correct_answer.accepted', ['H2O', 'water'])
        ->assertJsonPath('data.correct_answer.match', 'case_insensitive');
});

it('creates an essay question with evaluator instructions and no answer key', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['categories.manage', 'questions.manage']);
    Sanctum::actingAs($admin);
    $category = $this->createCategory($this->tenantA, 'General');

    $this->postJson('/api/v1/questions', [
        'category_id' => $category->category_id,
        'title' => 'Discuss',
        'type' => 'essay',
        'question_text' => 'Discuss the causes of WWI.',
        'bloom_level' => 4,
        'evaluator_instructions' => ['rubric_hint' => 'Award 2 points per cause', 'max_words' => 500],
    ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'essay')
        ->assertJsonCount(0, 'data.choices')
        ->assertJsonPath('data.correct_answer', null)
        ->assertJsonPath('data.evaluator_instructions.max_words', 500);
});

it('rejects a true/false question with no answer as 422, not 500', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['categories.manage', 'questions.manage']);
    Sanctum::actingAs($admin);
    $category = $this->createCategory($this->tenantA, 'General');

    $this->postJson('/api/v1/questions', [
        'category_id' => $category->category_id,
        'title' => 'Missing answer',
        'type' => 'true_false',
        'question_text' => 'Incomplete.',
        'bloom_level' => 1,
    ])->assertStatus(422);
});

/*
|--------------------------------------------------------------------------
| Priority #1 — true versioning
|--------------------------------------------------------------------------
*/
it('spawns a new immutable version on a content edit and preserves the prior one', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['categories.manage', 'questions.manage']);
    Sanctum::actingAs($admin);
    $category = $this->createCategory($this->tenantA, 'General');

    $created = $this->postJson('/api/v1/questions', [
        'category_id' => $category->category_id,
        'title' => 'Versioned',
        'type' => 'mcq',
        'question_text' => 'Original text?',
        'bloom_level' => 2,
        'choices' => [
            ['option_text' => 'A', 'is_correct' => true],
            ['option_text' => 'B', 'is_correct' => false],
        ],
    ])->assertCreated();

    $questionId = $created->json('data.id');
    $firstVersionId = $created->json('data.version_id');

    $this->patchJson('/api/v1/questions/' . $questionId, [
        'question_text' => 'Revised text?',
    ])
        ->assertOk()
        ->assertJsonPath('data.question_text', 'Revised text?');

    $question = Question::query()->findOrFail($questionId);
    $versions = QuestionVersion::query()
        ->where('question_id', $questionId)
        ->orderBy('ver_num')
        ->get();

    expect($versions)->toHaveCount(2);
    expect($versions->pluck('ver_num')->all())->toBe([1, 2]);
    expect((string) $question->current_version_id)->not->toBe((string) $firstVersionId);
    // v1 is untouched and still holds the original content.
    expect($versions->firstWhere('ver_num', 1)->question_text)->toBe('Original text?');
});

it('edits header-only fields in place without spawning a version', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['categories.manage', 'questions.manage']);
    Sanctum::actingAs($admin);
    $category = $this->createCategory($this->tenantA, 'General');

    $created = $this->postJson('/api/v1/questions', [
        'category_id' => $category->category_id,
        'title' => 'Title v1',
        'type' => 'mcq',
        'question_text' => 'Stable text?',
        'bloom_level' => 2,
        'choices' => [
            ['option_text' => 'A', 'is_correct' => true],
            ['option_text' => 'B', 'is_correct' => false],
        ],
    ])->assertCreated();

    $questionId = $created->json('data.id');

    $this->patchJson('/api/v1/questions/' . $questionId, ['title' => 'Title v2'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Title v2');

    expect(QuestionVersion::query()->where('question_id', $questionId)->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Priority #2 — soft delete retains history
|--------------------------------------------------------------------------
*/
it('soft-deletes a question and retains its versions', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['categories.manage', 'questions.manage']);
    Sanctum::actingAs($admin);
    $category = $this->createCategory($this->tenantA, 'General');

    $created = $this->postJson('/api/v1/questions', [
        'category_id' => $category->category_id,
        'title' => 'Disposable',
        'type' => 'mcq',
        'question_text' => 'Bye?',
        'bloom_level' => 1,
        'choices' => [
            ['option_text' => 'A', 'is_correct' => true],
            ['option_text' => 'B', 'is_correct' => false],
        ],
    ])->assertCreated();

    $questionId = $created->json('data.id');

    $this->deleteJson('/api/v1/questions/' . $questionId)->assertNoContent();

    // Hidden from normal queries…
    expect(Question::query()->find($questionId))->toBeNull();

    // …but retained with a deletion stamp, and its versions survive.
    $trashed = Question::withTrashed()->find($questionId);
    expect($trashed)->not->toBeNull();
    expect($trashed->deleted_at)->not->toBeNull();
    expect(QuestionVersion::query()->where('question_id', $questionId)->count())->toBeGreaterThan(0);
});

/*
|--------------------------------------------------------------------------
| Priority #5 — candidate resource never leaks the answer key
|--------------------------------------------------------------------------
|
| QuestionCandidateResource is a security boundary: a test-taker must never
| receive the correct answer, the per-option is_correct flag, option metadata
| (which encodes the answer for true/false), psychometrics, or evaluator
| instructions. We use a true/false question because it has the MOST to leak —
| an answer key, is_correct flags, and answer-bearing option metadata.
*/
it('hides answer keys and analytics in the candidate resource', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['categories.manage', 'questions.manage']);
    Sanctum::actingAs($admin);
    $category = $this->createCategory($this->tenantA, 'General');

    $id = $this->postJson('/api/v1/questions', [
        'category_id' => $category->category_id,
        'title' => 'Leak check',
        'type' => 'true_false',
        'question_text' => 'Water boils at 100°C at sea level.',
        'bloom_level' => 1,
        'correct_answer' => true,
        'psychometrics' => ['p_value' => 0.8, 'discrimination_index' => 0.5],
    ])->assertCreated()->json('data.id');

    $question = Question::query()
        ->with(['currentVersion.options', 'currentVersion.psychometrics'])
        ->findOrFail($id);

    $payload = (new QuestionCandidateResource($question))->toArray(request());

    // Sensitive fields must be absent entirely — not merely nulled.
    expect($payload)->not->toHaveKey('correct_answer')
        ->and($payload)->not->toHaveKey('psychometrics')
        ->and($payload)->not->toHaveKey('evaluator_instructions');

    // Choices are answerable but never reveal which option is correct.
    expect($payload['choices'])->toHaveCount(2);

    foreach ($payload['choices'] as $choice) {
        expect($choice)->toHaveKey('option_text')
            ->and($choice)->not->toHaveKey('is_correct')
            ->and($choice)->not->toHaveKey('option_metadata');
    }

    // Sanity: the candidate still gets what they need to answer.
    expect($payload)->toHaveKey('question_text')
        ->and($payload['type'])->toBe('true_false');
});
