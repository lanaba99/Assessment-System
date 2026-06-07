<?php

declare(strict_types=1);

use App\Domains\ExamEngine\Enums\ExamStatus;
use App\Domains\ExamSession\Contracts\ExamSessionService;
use App\Domains\ExamSession\Events\ExamSessionCompleted;
use App\Domains\ExamSession\Events\ResponseSubmitted;
use App\Domains\ExamSession\Exceptions\StaleVersionLockException;
use App\Domains\ExamSession\Models\CandidateExamStatus;
use App\Domains\Grading\Events\ResultGenerated;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\ExamSession\UsesExamSessionSchema;

uses(UsesExamSessionSchema::class);

/*
|--------------------------------------------------------------------------
| Database state
|--------------------------------------------------------------------------
|
| This suite uses UsesExamSessionSchema instead of RefreshDatabase. The
| trait rebuilds the full table stack from scratch on every beforeEach
| call via bootExamSessionSchema(), giving the same clean-slate guarantee
| as RefreshDatabase without running the full Laravel migration set
| (which contains MySQL-only operations incompatible with the SQLite
| in-memory test driver).
|
| Each test therefore starts with a completely empty database.
*/

beforeEach(function (): void {
    $this->bootExamSessionSchema();
    $this->withoutTenancyIdentificationMiddleware();
    $this->initializeTenantContext($this->tenantA);

    // Fake only domain events that have cross-domain side effects (grading,
    // psychometrics). Scoped faking preserves Eloquent model events so
    // UsesUuid UUID generation continues to work.
    Event::fake([
        ExamSessionCompleted::class,
        ResponseSubmitted::class,
        ResultGenerated::class,
    ]);
});

/*
|==========================================================================
| Test 1 — Race Condition Prevention (Pessimistic Lock / Idempotency)
|==========================================================================
|
| True multi-process concurrency cannot be replicated in a single-threaded
| PHP test. Instead, these tests verify the BEHAVIOURAL outcome that the
| pessimistic lock is designed to enforce:
|
|   Concurrent flow (production):
|     Request A: acquires lock → findActiveSession (null) → creates session → commits
|     Request B: blocks on lock → acquires lock → findActiveSession (finds A's session)
|                → returns A's session idempotently
|
|   Single-threaded simulation:
|     We manually commit the "first" session to the DB, then call startSession.
|     Gate 2 (idempotency check inside the lock scope) must find it and return it.
*/

it('returns the existing in_progress session when called concurrently (idempotency gate)', function (): void {
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($candidate, ['exam_sessions.start']);
    Sanctum::actingAs($candidate);

    [$exam] = $this->prepareExamWithMockedItems($this->tenantA, (string) $candidate->id);
    $enrollment = $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);

    // Simulate the committed session that "Request A" would have created.
    $existingSession = $this->createExamSession(
        $this->tenantA,
        (string) $exam->exam_id,
        (string) $enrollment->enrollment_id,
        (string) $candidate->id,
        ['session_state' => 'in_progress'],
    );

    // "Request B" arrives — Gate 2 (inside the lock scope) must find the
    // existing session and return it without creating a duplicate.
    $response = $this->postJson('/api/v1/exam-sessions/', [
        'exam_id' => (string) $exam->exam_id,
    ]);

    $response->assertCreated();

    // Must return the pre-existing session UUID, not a freshly generated one.
    expect($response->json('data.session_id'))->toBe((string) $existingSession->session_id);
});

it('never creates more than one session per (tenant, candidate, exam) regardless of repeat calls', function (): void {
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($candidate, ['exam_sessions.start']);
    Sanctum::actingAs($candidate);

    [$exam] = $this->prepareExamWithMockedItems($this->tenantA, (string) $candidate->id);
    $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);

    // Call startSession three times.
    $r1 = $this->postJson('/api/v1/exam-sessions/', ['exam_id' => (string) $exam->exam_id])->assertCreated();
    $r2 = $this->postJson('/api/v1/exam-sessions/', ['exam_id' => (string) $exam->exam_id])->assertCreated();
    $r3 = $this->postJson('/api/v1/exam-sessions/', ['exam_id' => (string) $exam->exam_id])->assertCreated();

    // All three responses must reference the same session.
    expect($r2->json('data.session_id'))->toBe($r1->json('data.session_id'));
    expect($r3->json('data.session_id'))->toBe($r1->json('data.session_id'));

    // Exactly one session row must exist in the database.
    expect(
        CandidateExamStatus::query()
            ->where('exam_id', $exam->exam_id)
            ->where('candidate_user_id', $candidate->id)
            ->count()
    )->toBe(1);
});

/*
|==========================================================================
| Test 2 — Zero-Response Termination Guard
|==========================================================================
|
| When a manager terminates a session that has zero recorded responses,
| ExamSessionCompleted must NOT be dispatched. This prevents the grading
| pipeline from creating a ghost zero-score AssessmentResult record for a
| session that was never actually used.
|
| Corollary: if the session HAS responses, the event must fire even when
| the actor is a manager (covered by ExamSessionLifecycleTest).
*/

it('does not dispatch ExamSessionCompleted when a manager terminates an empty session', function (): void {
    $admin = $this->createUser($this->tenantA);
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($admin, ['exam_sessions.manage']);
    Sanctum::actingAs($admin);

    [$exam] = $this->prepareExamWithMockedItems($this->tenantA, (string) $admin->id);
    $enrollment = $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);
    $session = $this->createExamSession(
        $this->tenantA,
        (string) $exam->exam_id,
        (string) $enrollment->enrollment_id,
        (string) $candidate->id,
        ['total_questions_responded' => 0],
    );

    $this->postJson('/api/v1/exam-sessions/' . $session->session_id . '/terminate')
        ->assertOk()
        ->assertJsonPath('data.state', 'terminated');

    // No grading pipeline should start. Ghost grade records must not be created.
    Event::assertNotDispatched(ExamSessionCompleted::class);
    // ResultGenerated depends on ExamSessionCompleted — also must not fire.
    Event::assertNotDispatched(ResultGenerated::class);

    // Verify the DB row is correctly set to terminated (state transition did happen).
    $fresh = CandidateExamStatus::query()->whereKey($session->session_id)->firstOrFail();
    expect($fresh->session_state)->toBe('terminated');
    expect($fresh->session_ended_at)->not->toBeNull();
});

it('does not dispatch ExamSessionCompleted when a manager completes an empty session', function (): void {
    $admin = $this->createUser($this->tenantA);
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($admin, ['exam_sessions.manage']);
    Sanctum::actingAs($admin);

    [$exam] = $this->prepareExamWithMockedItems($this->tenantA, (string) $admin->id);
    $enrollment = $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);
    $session = $this->createExamSession(
        $this->tenantA,
        (string) $exam->exam_id,
        (string) $enrollment->enrollment_id,
        (string) $candidate->id,
        ['total_questions_responded' => 0],
    );

    $this->postJson('/api/v1/exam-sessions/' . $session->session_id . '/complete')
        ->assertOk()
        ->assertJsonPath('data.state', 'completed');

    Event::assertNotDispatched(ExamSessionCompleted::class);
});

it('DOES dispatch ExamSessionCompleted when a manager terminates a session with responses', function (): void {
    $admin = $this->createUser($this->tenantA);
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($admin, ['exam_sessions.manage']);
    Sanctum::actingAs($admin);

    [$exam] = $this->prepareExamWithMockedItems($this->tenantA, (string) $admin->id);
    $enrollment = $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);
    $session = $this->createExamSession(
        $this->tenantA,
        (string) $exam->exam_id,
        (string) $enrollment->enrollment_id,
        (string) $candidate->id,
        ['total_questions_responded' => 3],  // session has recorded activity
    );

    $this->postJson('/api/v1/exam-sessions/' . $session->session_id . '/terminate')
        ->assertOk()
        ->assertJsonPath('data.state', 'terminated');

    // Session has responses — grading must run regardless of who terminates.
    Event::assertDispatched(ExamSessionCompleted::class, function (ExamSessionCompleted $e) use ($session): bool {
        return $e->sessionId === (string) $session->session_id;
    });
});

/*
|==========================================================================
| Test 3 — Stale Version Lock → 409 Conflict
|==========================================================================
|
| When two requests race to mutate the same session simultaneously, the
| second writer's UPDATE WHERE version_lock = expected will find 0 rows
| (because the first already incremented it). The service throws
| StaleVersionLockException; the controller must map it to 409 Conflict.
|
| Single-threaded simulation: mock ExamSessionService::completeSession to
| throw StaleVersionLockException, then verify the HTTP layer returns 409.
| This tests the controller's exception-to-status mapping independently of
| the actual race-condition mechanics (which are tested at the repository
| level in integration).
*/

it('returns 409 Conflict with stale_version_lock when a concurrent mutation is detected', function (): void {
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($candidate, ['exam_sessions.start']);
    Sanctum::actingAs($candidate);

    [$exam] = $this->prepareExamWithMockedItems($this->tenantA, (string) $candidate->id);
    $enrollment = $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);
    $session = $this->createExamSession(
        $this->tenantA,
        (string) $exam->exam_id,
        (string) $enrollment->enrollment_id,
        (string) $candidate->id,
        ['session_state' => 'in_progress', 'version_lock' => 0],
    );

    // Inject a mock that simulates the service detecting a concurrent
    // modification. The mock returns a real session model for the auth/policy
    // step (loadSessionModel) and throws on the state transition itself.
    $this->mock(ExamSessionService::class, function ($mock) use ($session): void {
        $mock->shouldReceive('loadSessionModel')
            ->andReturn($session);

        $mock->shouldReceive('completeSession')
            ->andThrow(
                StaleVersionLockException::forSession((string) $session->session_id, 0)
            );
    });

    $this->postJson('/api/v1/exam-sessions/' . $session->session_id . '/complete')
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'stale_version_lock')
        ->assertJsonPath('error.message', fn ($msg) => str_contains($msg, 'refresh'));
});

it('returns 409 Conflict for stale_version_lock on suspend as well', function (): void {
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($candidate, ['exam_sessions.start']);
    Sanctum::actingAs($candidate);

    [$exam] = $this->prepareExamWithMockedItems($this->tenantA, (string) $candidate->id);
    $enrollment = $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);
    $session = $this->createExamSession(
        $this->tenantA,
        (string) $exam->exam_id,
        (string) $enrollment->enrollment_id,
        (string) $candidate->id,
    );

    $this->mock(ExamSessionService::class, function ($mock) use ($session): void {
        $mock->shouldReceive('loadSessionModel')->andReturn($session);
        $mock->shouldReceive('suspendSession')
            ->andThrow(StaleVersionLockException::forSession((string) $session->session_id, 2));
    });

    $this->postJson('/api/v1/exam-sessions/' . $session->session_id . '/suspend')
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'stale_version_lock');
});

/*
|==========================================================================
| Test 4 — Server-Side Duration Enforcement → 422 exam_duration_exceeded
|==========================================================================
|
| After exam.total_duration_minutes has elapsed since session_started_at,
| the service must reject any further response submissions with a 422.
| This prevents candidates from submitting answers after the clock expires,
| even if their browser timer was manipulated.
*/

it('rejects submitResponse with 422 when the exam duration has been exceeded', function (): void {
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($candidate, ['exam_sessions.start']);
    Sanctum::actingAs($candidate);

    // Create a published exam with a 60-minute time limit.
    $exam = $this->createExam($this->tenantA, (string) $candidate->id, [
        'exam_status' => ExamStatus::Published,
        'is_published' => true,
        'total_duration_minutes' => 60,
        'is_adaptive_exam' => false,
    ]);

    $section = $this->createExamSection((string) $exam->exam_id, $this->tenantA);
    $versionId = $this->createQuestionVersionStub($this->tenantA);
    $enrollment = $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);

    // Create a session that started 61 minutes ago — 1 minute past the limit.
    $session = $this->createExamSession(
        $this->tenantA,
        (string) $exam->exam_id,
        (string) $enrollment->enrollment_id,
        (string) $candidate->id,
        [
            'session_state' => 'in_progress',
            'session_started_at' => now()->subMinutes(61),
        ],
    );

    $item = $this->createSessionItem(
        (string) $session->session_id,
        (string) $section->section_id,
        $versionId,
    );

    $this->postJson('/api/v1/exam-sessions/' . $session->session_id . '/responses', [
        'session_item_id' => (string) $item->session_item_id,
        'response_type' => 'single_choice',
        'selected_options' => ['option_a'],
    ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'exam_duration_exceeded');

    // No response event must be dispatched.
    Event::assertNotDispatched(ResponseSubmitted::class);
});

it('accepts submitResponse when the exam duration has NOT been exceeded', function (): void {
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($candidate, ['exam_sessions.start']);
    Sanctum::actingAs($candidate);

    $exam = $this->createExam($this->tenantA, (string) $candidate->id, [
        'exam_status' => ExamStatus::Published,
        'is_published' => true,
        'total_duration_minutes' => 60,
        'is_adaptive_exam' => false,
    ]);

    $section = $this->createExamSection((string) $exam->exam_id, $this->tenantA);
    $versionId = $this->createQuestionVersionStub($this->tenantA);
    $enrollment = $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);

    // Session started 30 minutes ago — still within the 60-minute window.
    $session = $this->createExamSession(
        $this->tenantA,
        (string) $exam->exam_id,
        (string) $enrollment->enrollment_id,
        (string) $candidate->id,
        [
            'session_state' => 'in_progress',
            'session_started_at' => now()->subMinutes(30),
        ],
    );

    $item = $this->createSessionItem(
        (string) $session->session_id,
        (string) $section->section_id,
        $versionId,
    );

    $this->postJson('/api/v1/exam-sessions/' . $session->session_id . '/responses', [
        'session_item_id' => (string) $item->session_item_id,
        'response_type' => 'single_choice',
        'selected_options' => ['option_a'],
    ])
        ->assertOk()
        ->assertJsonPath('data.progress.total_questions_responded', 1);
});

/*
|==========================================================================
| Test 5 — Proctor Permission: proctors can now suspend/resume (RBAC)
|==========================================================================
|
| After Phase 3 Task 1, exam_sessions.manage holders can call participate
| actions (suspend/resume/complete). This verifies the updated policy gate.
*/

it('allows a proctor with exam_sessions.manage to suspend an active session', function (): void {
    $proctor = $this->createUser($this->tenantA);
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($proctor, ['exam_sessions.manage']);
    Sanctum::actingAs($proctor);

    [$exam] = $this->prepareExamWithMockedItems($this->tenantA, (string) $proctor->id);
    $enrollment = $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);
    $session = $this->createExamSession(
        $this->tenantA,
        (string) $exam->exam_id,
        (string) $enrollment->enrollment_id,
        (string) $candidate->id,
        ['session_state' => 'in_progress'],
    );

    // Proctor (not the candidate) must be able to suspend via the 'participate' ability.
    $this->postJson('/api/v1/exam-sessions/' . $session->session_id . '/suspend')
        ->assertOk()
        ->assertJsonPath('data.state', 'paused');
});

it('denies suspend to a user with only exam_sessions.start and no ownership', function (): void {
    $intruder = $this->createUser($this->tenantA);
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($intruder, ['exam_sessions.start']); // has start, not manage
    Sanctum::actingAs($intruder);

    [$exam] = $this->prepareExamWithMockedItems($this->tenantA, (string) $intruder->id);
    $enrollment = $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);
    $session = $this->createExamSession(
        $this->tenantA,
        (string) $exam->exam_id,
        (string) $enrollment->enrollment_id,
        (string) $candidate->id,
    );

    // Neither owns the session nor has exam_sessions.manage → 403.
    $this->postJson('/api/v1/exam-sessions/' . $session->session_id . '/suspend')
        ->assertForbidden();
});
