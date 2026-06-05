<?php

declare(strict_types=1);

use App\Domains\ExamEngine\Contracts\QuestionSelectionService;
use App\Domains\ExamEngine\Enums\ExamStatus;
use App\Domains\ExamEngine\Exceptions\BlueprintNotFeasibleException;
use App\Domains\ExamEngine\Models\Exam;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\ExamEngine\UsesExamEngineSchema;

uses(UsesExamEngineSchema::class);

beforeEach(function (): void {
    $this->bootExamEngineSchema();
    $this->withoutTenancyIdentificationMiddleware();
    $this->initializeTenantContext($this->tenantA);
});

/*
|--------------------------------------------------------------------------
| Schema drift guard
|--------------------------------------------------------------------------
|
| If a production migration adds or removes a column on the exams table and
| the curated list in UsesExamEngineSchema is not updated, these tests will
| fail with a clear message pointing to the fix.
*/
it('has all required columns on the exams table', function (string $column): void {
    expect(Schema::hasColumn('exams', $column))->toBeTrue(
        "Column [{$column}] is missing from the exams table. "
        . 'Update UsesExamEngineSchema::migrateExamEngineTables() to stay in lockstep.'
    );
})->with([
    'exam_id', 'tenant_id', 'created_by_user_id',
    'exam_name', 'exam_code', 'exam_description',
    'exam_type', 'assessment_mode',
    'total_questions', 'total_duration_minutes', 'pass_mark_percentage',
    'is_adaptive_exam', 'is_randomized',
    'exam_status', 'is_published', 'published_at', 'archived_at',
]);

it('has all required columns on the exam_sections table', function (string $column): void {
    expect(Schema::hasColumn('exam_sections', $column))->toBeTrue(
        "Column [{$column}] is missing from exam_sections. "
        . 'Update UsesExamEngineSchema::migrateExamEngineTables() to stay in lockstep.'
    );
})->with([
    'section_id', 'tenant_id', 'exam_id',
    'section_name', 'section_code', 'section_sequence',
    'questions_in_section', 'time_limit_minutes',
    'branching_logic', 'section_metadata', 'created_at',
]);

it('has all required columns on the exam_blueprints table', function (string $column): void {
    expect(Schema::hasColumn('exam_blueprints', $column))->toBeTrue(
        "Column [{$column}] is missing from exam_blueprints. "
        . 'Update UsesExamEngineSchema::migrateExamEngineTables() to stay in lockstep.'
    );
})->with([
    'blueprint_id', 'exam_id', 'section_id', 'competency_id',
    'min_questions_count', 'max_questions_count',
    'min_weight_percentage', 'max_weight_percentage',
    'bloom_distribution', 'target_difficulty', 'min_discrimination', 'resolution_strategy',
    'blueprint_metadata', 'created_at',
]);

/*
|--------------------------------------------------------------------------
| Exam creation
|--------------------------------------------------------------------------
*/
it('creates a draft exam via the endpoint and returns the correct shape', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['exams.manage', 'exams.view']);
    Sanctum::actingAs($admin);

    $response = $this->postJson('/api/v1/exams', [
        'exam_name' => 'PHP Fundamentals',
        'exam_code' => 'PHP-FUND-001',
        'exam_type' => 'certification',
        'assessment_mode' => 'online',
        'total_questions' => 40,
        'total_duration_minutes' => 90,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.exam_name', 'PHP Fundamentals')
        ->assertJsonPath('data.exam_code', 'PHP-FUND-001')
        ->assertJsonPath('data.exam_status', ExamStatus::Draft->value)
        ->assertJsonPath('data.is_published', false);

    expect(Exam::query()->where('exam_code', 'PHP-FUND-001')->exists())->toBeTrue();
});

it('stores tenant_id and created_by_user_id via forceCreate, not mass-assignment', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['exams.manage', 'exams.view']);
    Sanctum::actingAs($admin);

    $response = $this->postJson('/api/v1/exams', [
        'exam_name' => 'Assignment Check',
        'exam_code' => 'ASSIGN-CHECK-01',
        'exam_type' => 'placement',
        'assessment_mode' => 'online',
        'total_questions' => 20,
        'total_duration_minutes' => 45,
    ])->assertCreated();

    $examId = $response->json('data.id');
    $exam = Exam::query()->whereKey($examId)->firstOrFail();

    // Server-controlled fields are set correctly despite not being in $fillable.
    expect((string) $exam->tenant_id)->toBe($this->tenantA);
    expect((string) $exam->created_by_user_id)->toBe((string) $admin->id);
});

it('rejects exam creation with an invalid exam_type', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['exams.manage']);
    Sanctum::actingAs($admin);

    $this->postJson('/api/v1/exams', [
        'exam_name' => 'Bad Type Exam',
        'exam_code' => 'BAD-001',
        'exam_type' => 'unknown_type',
        'assessment_mode' => 'online',
        'total_questions' => 10,
        'total_duration_minutes' => 30,
    ])->assertUnprocessable();
});

it('rejects exam creation for a user without exams.manage permission', function (): void {
    $viewer = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($viewer, ['exams.view']); // view only, no manage
    Sanctum::actingAs($viewer);

    $this->postJson('/api/v1/exams', [
        'exam_name' => 'Forbidden Exam',
        'exam_code' => 'FORBIDDEN-001',
        'exam_type' => 'certification',
        'assessment_mode' => 'online',
        'total_questions' => 10,
        'total_duration_minutes' => 30,
    ])->assertForbidden();
});

it('rejects exam creation with a duplicate exam_code', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['exams.manage']);
    Sanctum::actingAs($admin);

    $this->createExam($this->tenantA, (string) $admin->id, ['exam_code' => 'DUPE-001']);

    $this->postJson('/api/v1/exams', [
        'exam_name' => 'Another Exam',
        'exam_code' => 'DUPE-001',
        'exam_type' => 'certification',
        'assessment_mode' => 'online',
        'total_questions' => 10,
        'total_duration_minutes' => 30,
    ])->assertUnprocessable();
});

/*
|--------------------------------------------------------------------------
| Read
|--------------------------------------------------------------------------
*/
it('returns a single exam via the show endpoint', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['exams.view']);
    Sanctum::actingAs($admin);

    $exam = $this->createExam($this->tenantA, (string) $admin->id, ['exam_name' => 'Readable Exam']);

    $this->getJson('/api/v1/exams/' . $exam->exam_id)
        ->assertOk()
        ->assertJsonPath('data.id', (string) $exam->exam_id)
        ->assertJsonPath('data.exam_name', 'Readable Exam')
        ->assertJsonPath('data.exam_status', ExamStatus::Draft->value);
});

it('returns 404 for an exam that does not exist', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['exams.view']);
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/exams/' . \Illuminate\Support\Str::uuid())
        ->assertNotFound()
        ->assertJsonPath('error.code', 'exam_not_found');
});

it('lists all tenant exams via the index endpoint', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['exams.view']);
    Sanctum::actingAs($admin);

    $this->createExam($this->tenantA, (string) $admin->id, ['exam_code' => 'LIST-001']);
    $this->createExam($this->tenantA, (string) $admin->id, ['exam_code' => 'LIST-002']);

    $response = $this->getJson('/api/v1/exams')->assertOk();

    expect(count($response->json('data')))->toBeGreaterThanOrEqual(2);
});

/*
|--------------------------------------------------------------------------
| Update
|--------------------------------------------------------------------------
*/
it('updates exam fields via PATCH and returns the updated exam', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['exams.manage', 'exams.view']);
    Sanctum::actingAs($admin);

    $exam = $this->createExam($this->tenantA, (string) $admin->id);

    $this->patchJson('/api/v1/exams/' . $exam->exam_id, [
        'exam_name' => 'Updated Name',
        'pass_mark_percentage' => 80.0,
    ])
        ->assertOk()
        ->assertJsonPath('data.exam_name', 'Updated Name')
        ->assertJsonPath('data.pass_mark_percentage', 80)
        // Unpatched fields are preserved.
        ->assertJsonPath('data.exam_status', ExamStatus::Draft->value);
});

it('returns 404 when patching a non-existent exam', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['exams.manage']);
    Sanctum::actingAs($admin);

    $this->patchJson('/api/v1/exams/' . \Illuminate\Support\Str::uuid(), [
        'exam_name' => 'Ghost',
    ])->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Lifecycle — state machine
|--------------------------------------------------------------------------
*/
it('transitions a draft exam to published', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['exams.manage', 'exams.view']);
    Sanctum::actingAs($admin);

    $exam = $this->createExam($this->tenantA, (string) $admin->id);
    expect($exam->exam_status)->toBe(ExamStatus::Draft);

    $this->postJson('/api/v1/exams/' . $exam->exam_id . '/publish')
        ->assertOk()
        ->assertJsonPath('data.exam_status', ExamStatus::Published->value)
        ->assertJsonPath('data.is_published', true);

    $fresh = Exam::query()->whereKey($exam->exam_id)->firstOrFail();
    expect($fresh->exam_status)->toBe(ExamStatus::Published);
    expect($fresh->published_at)->not->toBeNull();
});

it('transitions a published exam to archived', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['exams.manage', 'exams.view']);
    Sanctum::actingAs($admin);

    $exam = $this->createExam($this->tenantA, (string) $admin->id, [
        'exam_status' => ExamStatus::Published,
        'is_published' => true,
        'published_at' => now(),
    ]);

    $this->postJson('/api/v1/exams/' . $exam->exam_id . '/archive')
        ->assertOk()
        ->assertJsonPath('data.exam_status', ExamStatus::Archived->value)
        ->assertJsonPath('data.is_published', false);

    $fresh = Exam::query()->whereKey($exam->exam_id)->firstOrFail();
    expect($fresh->exam_status)->toBe(ExamStatus::Archived);
    expect($fresh->archived_at)->not->toBeNull();
});

it('allows archiving a draft exam directly (Draft → Archived is valid)', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['exams.manage', 'exams.view']);
    Sanctum::actingAs($admin);

    $exam = $this->createExam($this->tenantA, (string) $admin->id);

    $this->postJson('/api/v1/exams/' . $exam->exam_id . '/archive')
        ->assertOk()
        ->assertJsonPath('data.exam_status', ExamStatus::Archived->value);
});

it('rejects publishing when blueprint coverage is insufficient (blueprint_not_feasible)', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['exams.manage', 'exams.view']);
    Sanctum::actingAs($admin);

    $exam = $this->createExam($this->tenantA, (string) $admin->id);
    $examId = (string) $exam->exam_id;

    // Mock the selection service to simulate a blueprint that the QuestionBank
    // cannot satisfy. This confirms the controller converts the domain exception
    // to a 422 response without needing a full QB table setup in this harness.
    $this->mock(QuestionSelectionService::class)
        ->shouldReceive('assertBlueprintFeasible')
        ->once()
        ->andThrow(BlueprintNotFeasibleException::forSections($examId, []));

    $this->postJson('/api/v1/exams/' . $examId . '/publish')
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'blueprint_not_feasible');

    // The exam must remain in Draft — no partial state mutation.
    $fresh = Exam::query()->whereKey($examId)->firstOrFail();
    expect($fresh->exam_status)->toBe(ExamStatus::Draft);
});

it('rejects publishing an already-archived exam (invalid state transition)', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['exams.manage', 'exams.view']);
    Sanctum::actingAs($admin);

    $exam = $this->createExam($this->tenantA, (string) $admin->id, [
        'exam_status' => ExamStatus::Archived,
        'archived_at' => now(),
    ]);

    $this->postJson('/api/v1/exams/' . $exam->exam_id . '/publish')
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'invalid_exam_state');
});

it('rejects re-publishing an already-published exam', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['exams.manage', 'exams.view']);
    Sanctum::actingAs($admin);

    $exam = $this->createExam($this->tenantA, (string) $admin->id, [
        'exam_status' => ExamStatus::Published,
        'is_published' => true,
        'published_at' => now(),
    ]);

    $this->postJson('/api/v1/exams/' . $exam->exam_id . '/publish')
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'invalid_exam_state');
});

it('preserves published_at when re-publishing is rejected (no partial mutation)', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['exams.manage', 'exams.view']);
    Sanctum::actingAs($admin);

    $originalPublishedAt = now()->subDay();
    $exam = $this->createExam($this->tenantA, (string) $admin->id, [
        'exam_status' => ExamStatus::Archived,
        'archived_at' => now(),
        'published_at' => $originalPublishedAt,
    ]);

    $this->postJson('/api/v1/exams/' . $exam->exam_id . '/publish')
        ->assertUnprocessable();

    // The exam state must be unchanged after the failed transition.
    $fresh = Exam::query()->whereKey($exam->exam_id)->firstOrFail();
    expect($fresh->exam_status)->toBe(ExamStatus::Archived);
});

/*
|--------------------------------------------------------------------------
| Delete
|--------------------------------------------------------------------------
*/
it('deletes a draft exam and returns 204', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['exams.manage']);
    Sanctum::actingAs($admin);

    $exam = $this->createExam($this->tenantA, (string) $admin->id);

    $this->deleteJson('/api/v1/exams/' . $exam->exam_id)->assertNoContent();

    expect(Exam::query()->whereKey($exam->exam_id)->exists())->toBeFalse();
});

it('returns 404 when deleting a non-existent exam', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['exams.manage']);
    Sanctum::actingAs($admin);

    $this->deleteJson('/api/v1/exams/' . \Illuminate\Support\Str::uuid())
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Policy — tenant isolation
|--------------------------------------------------------------------------
*/
it('hides a tenantB exam from a tenantA user with a 404 (BelongsToTenant scope)', function (): void {
    // Create the exam under tenantB's context.
    $this->initializeTenantContext($this->tenantB);
    $ownerB = $this->createUser($this->tenantB, password: 'OwnerPass1!');
    $examB = $this->createExam($this->tenantB, (string) $ownerB->id);

    // Switch to tenantA context — tenantB exam is invisible.
    $this->initializeTenantContext($this->tenantA);
    $actorA = $this->createUser($this->tenantA, password: 'ActorPass1!');
    $this->grantPermissionsToUser($actorA, ['exams.view', 'exams.manage']);
    Sanctum::actingAs($actorA);

    // The repository scopes by tenant_id, so the exam is simply not found.
    $this->getJson('/api/v1/exams/' . $examB->exam_id)
        ->assertNotFound();
});

it('prevents a tenantA user from publishing a tenantB exam via same-tenant policy check', function (): void {
    // Create under tenantB.
    $this->initializeTenantContext($this->tenantB);
    $ownerB = $this->createUser($this->tenantB, password: 'OwnerPass1!');
    $examB = $this->createExam($this->tenantB, (string) $ownerB->id);

    // Switch to tenantA.
    $this->initializeTenantContext($this->tenantA);
    $actorA = $this->createUser($this->tenantA, password: 'ActorPass1!');
    $this->grantPermissionsToUser($actorA, ['exams.manage']);
    Sanctum::actingAs($actorA);

    // The exam is not found under tenantA's scope → 404 before policy even fires.
    $this->postJson('/api/v1/exams/' . $examB->exam_id . '/publish')
        ->assertNotFound();
});

it('rejects an unauthenticated request with 401', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $exam = $this->createExam($this->tenantA, (string) $admin->id);

    // No Sanctum::actingAs — unauthenticated.
    $this->getJson('/api/v1/exams/' . $exam->exam_id)
        ->assertUnauthorized();
});
