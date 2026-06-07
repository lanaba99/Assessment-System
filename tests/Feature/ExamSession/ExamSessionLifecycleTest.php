<?php

declare(strict_types=1);

use App\Domains\ExamEngine\Enums\ExamStatus;
use App\Domains\ExamSession\Events\ExamSessionCompleted;
use App\Domains\ExamSession\Models\CandidateExamStatus;
use App\Domains\ExamSession\Models\ExamCandidateEligible;
use App\Domains\ExamSession\Models\ExamSessionItem;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\ExamSession\UsesExamSessionSchema;

uses(UsesExamSessionSchema::class);

beforeEach(function (): void {
    $this->bootExamSessionSchema();
    $this->withoutTenancyIdentificationMiddleware();
    $this->initializeTenantContext($this->tenantA);

    // Fake only the two domain events that trigger cross-domain listeners
    // (Grading finalization, psychometrics recalculation). Scoped faking avoids
    // intercepting Eloquent model events (eloquent.creating etc.), which would
    // break UsesUuid UUID generation.
    Event::fake([
        \App\Domains\ExamSession\Events\ExamSessionCompleted::class,
        \App\Domains\ExamSession\Events\ResponseSubmitted::class,
    ]);
});

/*
|--------------------------------------------------------------------------
| Schema drift guards
|--------------------------------------------------------------------------
*/
it('has all required columns on the exam_enrollments table', function (string $column): void {
    expect(Schema::hasColumn('exam_enrollments', $column))->toBeTrue(
        "Column [{$column}] missing from exam_enrollments — update UsesExamSessionSchema."
    );
})->with([
    'enrollment_id', 'exam_id', 'candidate_user_id', 'tenant_id', 'cohort_id',
    'enrollment_status', 'enrollment_date', 'start_window_date', 'end_window_date',
    'can_retake_exam', 'max_attempts_allowed', 'attempts_used', 'attempts_remaining',
    'highest_score_achieved', 'highest_score_status', 'enrollment_notes',
]);

it('has all required columns on the exam_sessions table', function (string $column): void {
    expect(Schema::hasColumn('exam_sessions', $column))->toBeTrue(
        "Column [{$column}] missing from exam_sessions — update UsesExamSessionSchema."
    );
})->with([
    'session_id', 'exam_id', 'enrollment_id', 'candidate_user_id', 'tenant_id',
    'session_state', 'current_question_index', 'total_questions_responded',
    'total_questions_flagged', 'session_started_at', 'session_ended_at',
    'completion_method', 'version_lock', 'last_heartbeat_at',
]);

it('has all required columns on the exam_session_items table', function (string $column): void {
    expect(Schema::hasColumn('exam_session_items', $column))->toBeTrue(
        "Column [{$column}] missing from exam_session_items — update UsesExamSessionSchema."
    );
})->with([
    'session_item_id', 'session_id', 'section_id', 'question_version_id',
    'sequence_number', 'item_state', 'answered_at', 'is_flagged', 'version_lock',
]);

/*
|--------------------------------------------------------------------------
| Happy path — start session
|--------------------------------------------------------------------------
*/
it('starts a session and returns in_progress state', function (): void {
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($candidate, ['exam_sessions.start']);
    Sanctum::actingAs($candidate);

    [$exam] = $this->prepareExamWithMockedItems($this->tenantA, (string) $candidate->id);
    $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);

    $response = $this->postJson('/api/v1/exam-sessions/', [
        'exam_id' => (string) $exam->exam_id,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.state', 'in_progress')
        ->assertJsonPath('data.exam_id', (string) $exam->exam_id)
        ->assertJsonPath('data.candidate_id', (string) $candidate->id);

    // One item was pre-populated by the mock.
    $sessionId = $response->json('data.session_id');
    expect(ExamSessionItem::query()->where('session_id', $sessionId)->count())->toBe(1);
});

it('derives candidate_id from the authenticated user, never from the request body', function (): void {
    $candidate = $this->createUser($this->tenantA);
    $attacker = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($candidate, ['exam_sessions.start']);
    Sanctum::actingAs($candidate);

    [$exam] = $this->prepareExamWithMockedItems($this->tenantA, (string) $candidate->id);
    $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);

    // Even if attacker's UUID is passed in the body, the session must belong
    // to the authenticated candidate, not the body value.
    $response = $this->postJson('/api/v1/exam-sessions/', [
        'exam_id' => (string) $exam->exam_id,
        'candidate_id' => (string) $attacker->id, // must be ignored
    ])->assertCreated();

    expect($response->json('data.candidate_id'))->toBe((string) $candidate->id);

    $session = CandidateExamStatus::query()
        ->whereKey($response->json('data.session_id'))
        ->firstOrFail();
    expect((string) $session->candidate_user_id)->toBe((string) $candidate->id);
});

it('start is idempotent and returns the existing session on re-call', function (): void {
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($candidate, ['exam_sessions.start']);
    Sanctum::actingAs($candidate);

    [$exam] = $this->prepareExamWithMockedItems($this->tenantA, (string) $candidate->id);
    $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);

    $first = $this->postJson('/api/v1/exam-sessions/', ['exam_id' => (string) $exam->exam_id])
        ->assertCreated();

    // Mock should NOT be called a second time.
    $this->mock(QuestionSelectionService::class)
        ->shouldNotReceive('resolveQuestionsForSession');

    $second = $this->postJson('/api/v1/exam-sessions/', ['exam_id' => (string) $exam->exam_id])
        ->assertCreated();

    expect($first->json('data.session_id'))->toBe($second->json('data.session_id'));
    expect(CandidateExamStatus::query()->where('exam_id', $exam->exam_id)->count())->toBe(1);
});

it('returns the session via the show endpoint', function (): void {
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($candidate, ['exam_sessions.start', 'exam_sessions.view']);
    Sanctum::actingAs($candidate);

    [$exam] = $this->prepareExamWithMockedItems($this->tenantA, (string) $candidate->id);
    $enrollment = $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);
    $session = $this->createExamSession(
        $this->tenantA, (string) $exam->exam_id,
        (string) $enrollment->enrollment_id, (string) $candidate->id,
    );

    $this->getJson('/api/v1/exam-sessions/' . $session->session_id)
        ->assertOk()
        ->assertJsonPath('data.session_id', (string) $session->session_id)
        ->assertJsonPath('data.state', 'in_progress');
});

/*
|--------------------------------------------------------------------------
| Happy path — submit response
|--------------------------------------------------------------------------
*/
it('submits a response, increments the counter, and returns the updated session', function (): void {
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($candidate, ['exam_sessions.start']);
    Sanctum::actingAs($candidate);

    [$exam, $section, $versionId] = $this->prepareExamWithMockedItems($this->tenantA, (string) $candidate->id);
    $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);

    $start = $this->postJson('/api/v1/exam-sessions/', ['exam_id' => (string) $exam->exam_id])
        ->assertCreated();

    $sessionId = $start->json('data.session_id');
    $sessionItemId = $start->json('data.current.session_item_id');

    $this->postJson('/api/v1/exam-sessions/' . $sessionId . '/responses', [
        'session_item_id' => $sessionItemId,
        'response_type' => 'single_choice',
        'selected_options' => ['option_a'],
        'time_spent_seconds' => 45,
        'is_flagged_for_review' => false,
    ])
        ->assertOk()
        ->assertJsonPath('data.progress.total_questions_responded', 1)
        ->assertJsonPath('data.progress.total_questions_flagged', 0);

    // Verify the item is now 'answered' in the database.
    $item = ExamSessionItem::query()->whereKey($sessionItemId)->firstOrFail();
    expect($item->item_state)->toBe('answered');
    expect($item->answered_at)->not->toBeNull();
});

it('increments the flagged counter when is_flagged_for_review is true', function (): void {
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($candidate, ['exam_sessions.start']);
    Sanctum::actingAs($candidate);

    [$exam] = $this->prepareExamWithMockedItems($this->tenantA, (string) $candidate->id);
    $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);

    $start = $this->postJson('/api/v1/exam-sessions/', ['exam_id' => (string) $exam->exam_id])
        ->assertCreated();

    $sessionId = $start->json('data.session_id');
    $sessionItemId = $start->json('data.current.session_item_id');

    $this->postJson('/api/v1/exam-sessions/' . $sessionId . '/responses', [
        'session_item_id' => $sessionItemId,
        'response_type' => 'single_choice',
        'is_flagged_for_review' => true,
    ])
        ->assertOk()
        ->assertJsonPath('data.progress.total_questions_flagged', 1);
});

/*
|--------------------------------------------------------------------------
| Happy path — complete session (full lifecycle)
|--------------------------------------------------------------------------
*/
it('completes the full lifecycle: start → submit → complete', function (): void {
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($candidate, ['exam_sessions.start']);
    Sanctum::actingAs($candidate);

    [$exam] = $this->prepareExamWithMockedItems($this->tenantA, (string) $candidate->id);
    $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);

    // Start
    $start = $this->postJson('/api/v1/exam-sessions/', ['exam_id' => (string) $exam->exam_id])
        ->assertCreated();
    $sessionId = $start->json('data.session_id');
    $sessionItemId = $start->json('data.current.session_item_id');

    // Submit
    $this->postJson('/api/v1/exam-sessions/' . $sessionId . '/responses', [
        'session_item_id' => $sessionItemId,
        'response_type' => 'single_choice',
        'selected_options' => ['option_b'],
    ])->assertOk();

    // Complete
    $this->postJson('/api/v1/exam-sessions/' . $sessionId . '/complete')
        ->assertOk()
        ->assertJsonPath('data.state', 'completed');

    // Verify the DB row matches.
    $session = CandidateExamStatus::query()->whereKey($sessionId)->firstOrFail();
    expect($session->session_state)->toBe('completed');
    expect($session->session_ended_at)->not->toBeNull();
    expect($session->completion_method)->toBe('candidate_submitted');

    // Downstream grading pipeline must be triggered.
    Event::assertDispatched(ExamSessionCompleted::class, function (ExamSessionCompleted $event) use ($sessionId): bool {
        return $event->sessionId === $sessionId
            && $event->finalState === 'completed'
            && $event->completionMethod === 'candidate_submitted';
    });
});

/*
|--------------------------------------------------------------------------
| State machine — suspend / resume / terminate
|--------------------------------------------------------------------------
*/
it('suspends and resumes a session', function (): void {
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($candidate, ['exam_sessions.start']);
    Sanctum::actingAs($candidate);

    [$exam] = $this->prepareExamWithMockedItems($this->tenantA, (string) $candidate->id);
    $enrollment = $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);
    $session = $this->createExamSession(
        $this->tenantA, (string) $exam->exam_id,
        (string) $enrollment->enrollment_id, (string) $candidate->id,
        ['session_state' => 'in_progress'],
    );

    $this->postJson('/api/v1/exam-sessions/' . $session->session_id . '/suspend')
        ->assertOk()
        ->assertJsonPath('data.state', 'paused');

    $this->postJson('/api/v1/exam-sessions/' . $session->session_id . '/resume')
        ->assertOk()
        ->assertJsonPath('data.state', 'in_progress');
});

it('cannot submit a response to a suspended session', function (): void {
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($candidate, ['exam_sessions.start']);
    Sanctum::actingAs($candidate);

    [$exam, $section, $versionId] = $this->prepareExamWithMockedItems($this->tenantA, (string) $candidate->id);
    $enrollment = $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);
    $session = $this->createExamSession(
        $this->tenantA, (string) $exam->exam_id,
        (string) $enrollment->enrollment_id, (string) $candidate->id,
        ['session_state' => 'paused'],
    );
    $item = $this->createSessionItem(
        (string) $session->session_id, (string) $section->section_id, $versionId,
    );

    $this->postJson('/api/v1/exam-sessions/' . $session->session_id . '/responses', [
        'session_item_id' => (string) $item->session_item_id,
        'response_type' => 'single_choice',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'invalid_session_state');
});

it('cannot complete an already-completed session', function (): void {
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($candidate, ['exam_sessions.start']);
    Sanctum::actingAs($candidate);

    [$exam] = $this->prepareExamWithMockedItems($this->tenantA, (string) $candidate->id);
    $enrollment = $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);
    $session = $this->createExamSession(
        $this->tenantA, (string) $exam->exam_id,
        (string) $enrollment->enrollment_id, (string) $candidate->id,
        ['session_state' => 'completed', 'session_ended_at' => now()],
    );

    $this->postJson('/api/v1/exam-sessions/' . $session->session_id . '/complete')
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'invalid_session_state');
});

it('terminates any non-terminal session and fires the completion event when responses exist', function (): void {
    $admin = $this->createUser($this->tenantA);
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($admin, ['exam_sessions.manage']);
    Sanctum::actingAs($admin);

    [$exam] = $this->prepareExamWithMockedItems($this->tenantA, (string) $admin->id);
    $enrollment = $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);
    // Session has one recorded response → zero-response guard must NOT suppress the event.
    $session = $this->createExamSession(
        $this->tenantA, (string) $exam->exam_id,
        (string) $enrollment->enrollment_id, (string) $candidate->id,
        ['total_questions_responded' => 1],
    );

    $this->postJson('/api/v1/exam-sessions/' . $session->session_id . '/terminate')
        ->assertOk()
        ->assertJsonPath('data.state', 'terminated');

    Event::assertDispatched(ExamSessionCompleted::class, function (ExamSessionCompleted $e) use ($session): bool {
        return $e->sessionId === (string) $session->session_id
            && $e->completionMethod === 'terminated';
    });
});

it('terminates an empty session without firing ExamSessionCompleted (zero-response guard)', function (): void {
    $admin = $this->createUser($this->tenantA);
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($admin, ['exam_sessions.manage']);
    Sanctum::actingAs($admin);

    [$exam] = $this->prepareExamWithMockedItems($this->tenantA, (string) $admin->id);
    $enrollment = $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);
    // Session has zero responses — manager terminating it must not create a ghost grade record.
    $session = $this->createExamSession(
        $this->tenantA, (string) $exam->exam_id,
        (string) $enrollment->enrollment_id, (string) $candidate->id,
        ['total_questions_responded' => 0],
    );

    $this->postJson('/api/v1/exam-sessions/' . $session->session_id . '/terminate')
        ->assertOk()
        ->assertJsonPath('data.state', 'terminated');

    // Event must be suppressed — no grading pipeline should run for an unused session.
    Event::assertNotDispatched(ExamSessionCompleted::class);
});

/*
|--------------------------------------------------------------------------
| Eligibility gate — 422 scenarios
|--------------------------------------------------------------------------
*/
it('rejects start when the exam is not published (draft)', function (): void {
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($candidate, ['exam_sessions.start']);
    Sanctum::actingAs($candidate);

    // BelongsToTenant is NOT active in tests; use explicit tenantId in createExam.
    $exam = $this->createExam($this->tenantA, (string) $candidate->id, [
        'exam_status' => ExamStatus::Draft,
        'is_published' => false,
    ]);

    $this->postJson('/api/v1/exam-sessions/', ['exam_id' => (string) $exam->exam_id])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'eligibility_violation');
});

it('returns 404 when there is no enrollment for this candidate and exam', function (): void {
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($candidate, ['exam_sessions.start']);
    Sanctum::actingAs($candidate);

    $exam = $this->createExam($this->tenantA, (string) $candidate->id, [
        'exam_status' => ExamStatus::Published,
        'is_published' => true,
    ]);
    // Deliberately no enrollment.

    $this->postJson('/api/v1/exam-sessions/', ['exam_id' => (string) $exam->exam_id])
        ->assertNotFound()
        ->assertJsonPath('error.code', 'enrollment_not_found');
});

it('rejects start when the enrollment is not active (e.g. revoked)', function (): void {
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($candidate, ['exam_sessions.start']);
    Sanctum::actingAs($candidate);

    $exam = $this->createExam($this->tenantA, (string) $candidate->id, [
        'exam_status' => ExamStatus::Published,
        'is_published' => true,
    ]);
    $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id, [
        'enrollment_status' => 'revoked',
    ]);

    $this->postJson('/api/v1/exam-sessions/', ['exam_id' => (string) $exam->exam_id])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'eligibility_violation');
});

it('rejects start when the eligibility window has expired', function (): void {
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($candidate, ['exam_sessions.start']);
    Sanctum::actingAs($candidate);

    $exam = $this->createExam($this->tenantA, (string) $candidate->id, [
        'exam_status' => ExamStatus::Published,
        'is_published' => true,
    ]);
    $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id, [
        'end_window_date' => now()->subDay(), // window closed yesterday
    ]);

    $this->postJson('/api/v1/exam-sessions/', ['exam_id' => (string) $exam->exam_id])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'eligibility_violation');
});

it('rejects start when the window has not opened yet', function (): void {
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($candidate, ['exam_sessions.start']);
    Sanctum::actingAs($candidate);

    $exam = $this->createExam($this->tenantA, (string) $candidate->id, [
        'exam_status' => ExamStatus::Published,
        'is_published' => true,
    ]);
    $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id, [
        'start_window_date' => now()->addDay(), // window opens tomorrow
    ]);

    $this->postJson('/api/v1/exam-sessions/', ['exam_id' => (string) $exam->exam_id])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'eligibility_violation');
});

it('rejects start when the candidate has exhausted their attempts', function (): void {
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($candidate, ['exam_sessions.start']);
    Sanctum::actingAs($candidate);

    $exam = $this->createExam($this->tenantA, (string) $candidate->id, [
        'exam_status' => ExamStatus::Published,
        'is_published' => true,
    ]);
    $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id, [
        'max_attempts_allowed' => 1,
        'attempts_used' => 1,  // all used up
        'attempts_remaining' => 0,
    ]);

    $this->postJson('/api/v1/exam-sessions/', ['exam_id' => (string) $exam->exam_id])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'eligibility_violation');
});

it('rejects start when the candidate is not a member of the required cohort', function (): void {
    $admin = $this->createUser($this->tenantA);
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($candidate, ['exam_sessions.start']);
    Sanctum::actingAs($candidate);

    $exam = $this->createExam($this->tenantA, (string) $admin->id, [
        'exam_status' => ExamStatus::Published,
        'is_published' => true,
    ]);
    $cohort = $this->createCohort($this->tenantA, (string) $admin->id);
    // Enrollment scoped to cohort, but candidate is NOT a member.
    $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id, [
        'cohort_id' => (string) $cohort->cohort_id,
    ]);

    $this->postJson('/api/v1/exam-sessions/', ['exam_id' => (string) $exam->exam_id])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'eligibility_violation');
});

it('allows start when the candidate IS an active cohort member', function (): void {
    $admin = $this->createUser($this->tenantA);
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($candidate, ['exam_sessions.start']);
    Sanctum::actingAs($candidate);

    [$exam] = $this->prepareExamWithMockedItems($this->tenantA, (string) $admin->id);
    $cohort = $this->createCohort($this->tenantA, (string) $admin->id);
    $this->createCohortMember($this->tenantA, (string) $cohort->cohort_id, (string) $candidate->id);
    $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id, [
        'cohort_id' => (string) $cohort->cohort_id,
    ]);

    $this->postJson('/api/v1/exam-sessions/', ['exam_id' => (string) $exam->exam_id])
        ->assertCreated()
        ->assertJsonPath('data.state', 'in_progress');
});

/*
|--------------------------------------------------------------------------
| Permission checks — 401 / 403
|--------------------------------------------------------------------------
*/
it('returns 401 for an unauthenticated start request', function (): void {
    $admin = $this->createUser($this->tenantA);
    $exam = $this->createExam($this->tenantA, (string) $admin->id, [
        'exam_status' => ExamStatus::Published,
        'is_published' => true,
    ]);

    // No Sanctum::actingAs — anonymous request.
    $this->postJson('/api/v1/exam-sessions/', ['exam_id' => (string) $exam->exam_id])
        ->assertUnauthorized();
});

it('returns 403 when the actor lacks exam_sessions.start permission', function (): void {
    $candidate = $this->createUser($this->tenantA);
    // No permission granted.
    Sanctum::actingAs($candidate);

    $exam = $this->createExam($this->tenantA, (string) $candidate->id, [
        'exam_status' => ExamStatus::Published,
        'is_published' => true,
    ]);
    $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);

    $this->postJson('/api/v1/exam-sessions/', ['exam_id' => (string) $exam->exam_id])
        ->assertForbidden();
});

it('returns 403 when a candidate tries to submit on another candidate\'s session', function (): void {
    $owner = $this->createUser($this->tenantA);
    $intruder = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($intruder, ['exam_sessions.start']);
    Sanctum::actingAs($intruder);

    [$exam, $section, $versionId] = $this->prepareExamWithMockedItems($this->tenantA, (string) $owner->id);
    $enrollment = $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $owner->id);
    $session = $this->createExamSession(
        $this->tenantA, (string) $exam->exam_id,
        (string) $enrollment->enrollment_id, (string) $owner->id,
    );
    $item = $this->createSessionItem(
        (string) $session->session_id, (string) $section->section_id, $versionId,
    );

    // Intruder (not the session owner) cannot use the 'participate' ability.
    $this->postJson('/api/v1/exam-sessions/' . $session->session_id . '/responses', [
        'session_item_id' => (string) $item->session_item_id,
        'response_type' => 'single_choice',
    ])->assertForbidden();
});

it('returns 403 when an actor without manage permission tries to terminate', function (): void {
    $candidate = $this->createUser($this->tenantA);
    // Only 'start', not 'manage'.
    $this->grantPermissionsToUser($candidate, ['exam_sessions.start']);
    Sanctum::actingAs($candidate);

    [$exam] = $this->prepareExamWithMockedItems($this->tenantA, (string) $candidate->id);
    $enrollment = $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);
    $session = $this->createExamSession(
        $this->tenantA, (string) $exam->exam_id,
        (string) $enrollment->enrollment_id, (string) $candidate->id,
    );

    // terminate requires 'manage' ability from the policy.
    $this->postJson('/api/v1/exam-sessions/' . $session->session_id . '/terminate')
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Tenant isolation — all cross-tenant actions must return 404
|--------------------------------------------------------------------------
*/
it('hides a tenantB session from a tenantA user (repository scope returns 404)', function (): void {
    // Build a session in tenantB.
    $this->initializeTenantContext($this->tenantB);
    $ownerB = $this->createUser($this->tenantB);
    $examB = $this->createExam($this->tenantB, (string) $ownerB->id, [
        'exam_status' => ExamStatus::Published, 'is_published' => true,
    ]);
    $enrollB = $this->createEnrollment($this->tenantB, (string) $examB->exam_id, (string) $ownerB->id);
    $sessionB = $this->createExamSession(
        $this->tenantB, (string) $examB->exam_id,
        (string) $enrollB->enrollment_id, (string) $ownerB->id,
    );

    // Switch to tenantA and try to read tenantB's session.
    $this->initializeTenantContext($this->tenantA);
    $actorA = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($actorA, ['exam_sessions.view']);
    Sanctum::actingAs($actorA);

    $this->getJson('/api/v1/exam-sessions/' . $sessionB->session_id)
        ->assertNotFound();
});

it('prevents tenantA from submitting on a tenantB session', function (): void {
    // Build session in tenantB.
    $this->initializeTenantContext($this->tenantB);
    $ownerB = $this->createUser($this->tenantB);
    $examB = $this->createExam($this->tenantB, (string) $ownerB->id, [
        'exam_status' => ExamStatus::Published, 'is_published' => true,
    ]);
    $enrollB = $this->createEnrollment($this->tenantB, (string) $examB->exam_id, (string) $ownerB->id);
    $sessionB = $this->createExamSession(
        $this->tenantB, (string) $examB->exam_id,
        (string) $enrollB->enrollment_id, (string) $ownerB->id,
    );

    // Switch to tenantA.
    $this->initializeTenantContext($this->tenantA);
    $actorA = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($actorA, ['exam_sessions.start']);
    Sanctum::actingAs($actorA);

    // loadSessionModel will not find sessionB under tenantA scope → 404.
    $this->postJson('/api/v1/exam-sessions/' . $sessionB->session_id . '/responses', [
        'session_item_id' => (string) Str::uuid(),
        'response_type' => 'single_choice',
    ])->assertNotFound();
});

it('prevents tenantA from starting a session on a tenantB exam', function (): void {
    // examB lives in tenantB.
    $this->initializeTenantContext($this->tenantB);
    $adminB = $this->createUser($this->tenantB);
    $examB = $this->createExam($this->tenantB, (string) $adminB->id, [
        'exam_status' => ExamStatus::Published, 'is_published' => true,
    ]);

    // actorA has permission but cannot reach examB.
    $this->initializeTenantContext($this->tenantA);
    $actorA = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($actorA, ['exam_sessions.start']);
    Sanctum::actingAs($actorA);

    // ExamRepository::findWithSectionsAndBlueprints filters by tenantId,
    // so examB is invisible → examNotPublished exception → 422.
    $this->postJson('/api/v1/exam-sessions/', ['exam_id' => (string) $examB->exam_id])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'eligibility_violation');
});

it('index for exam-sessions result returns 404 for a tenantB session from tenantA', function (): void {
    $this->initializeTenantContext($this->tenantB);
    $ownerB = $this->createUser($this->tenantB);
    $examB = $this->createExam($this->tenantB, (string) $ownerB->id, [
        'exam_status' => ExamStatus::Published, 'is_published' => true,
    ]);
    $enrollB = $this->createEnrollment($this->tenantB, (string) $examB->exam_id, (string) $ownerB->id);
    $sessionB = $this->createExamSession(
        $this->tenantB, (string) $examB->exam_id,
        (string) $enrollB->enrollment_id, (string) $ownerB->id,
    );

    $this->initializeTenantContext($this->tenantA);
    $actorA = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($actorA, ['exam_sessions.manage']);
    Sanctum::actingAs($actorA);

    $this->postJson('/api/v1/exam-sessions/' . $sessionB->session_id . '/terminate')
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Enrollment management
|--------------------------------------------------------------------------
*/
it('enrolls a candidate via the enrollment endpoint', function (): void {
    $admin = $this->createUser($this->tenantA);
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($admin, ['exam_sessions.manage']);
    Sanctum::actingAs($admin);

    $exam = $this->createExam($this->tenantA, (string) $admin->id, [
        'exam_status' => ExamStatus::Published,
        'is_published' => true,
    ]);

    $response = $this->postJson('/api/v1/exams/' . $exam->exam_id . '/enrollments', [
        'candidate_user_id' => (string) $candidate->id,
        'max_attempts_allowed' => 2,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.candidate_user_id', (string) $candidate->id)
        ->assertJsonPath('data.enrollment_status', 'active')
        ->assertJsonPath('data.max_attempts_allowed', 2);

    expect(
        ExamCandidateEligible::query()
            ->where('exam_id', $exam->exam_id)
            ->where('candidate_user_id', $candidate->id)
            ->exists()
    )->toBeTrue();
});

it('rejects a duplicate enrollment with 409', function (): void {
    $admin = $this->createUser($this->tenantA);
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($admin, ['exam_sessions.manage']);
    Sanctum::actingAs($admin);

    $exam = $this->createExam($this->tenantA, (string) $admin->id, [
        'exam_status' => ExamStatus::Published,
        'is_published' => true,
    ]);
    // First enrollment already exists.
    $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);

    $this->postJson('/api/v1/exams/' . $exam->exam_id . '/enrollments', [
        'candidate_user_id' => (string) $candidate->id,
    ])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'enrollment_already_exists');
});

it('revokes an enrollment and returns 204', function (): void {
    $admin = $this->createUser($this->tenantA);
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($admin, ['exam_sessions.manage']);
    Sanctum::actingAs($admin);

    $exam = $this->createExam($this->tenantA, (string) $admin->id, [
        'exam_status' => ExamStatus::Published,
        'is_published' => true,
    ]);
    $enrollment = $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);

    $this->deleteJson('/api/v1/exams/' . $exam->exam_id . '/enrollments/' . $enrollment->enrollment_id)
        ->assertNoContent();

    $fresh = ExamCandidateEligible::query()->whereKey($enrollment->enrollment_id)->firstOrFail();
    expect($fresh->enrollment_status)->toBe('revoked');
});

it('returns 403 when a non-manager tries to access the enrollment list', function (): void {
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($candidate, ['exam_sessions.start']);
    Sanctum::actingAs($candidate);

    $exam = $this->createExam($this->tenantA, (string) $candidate->id, [
        'exam_status' => ExamStatus::Published,
        'is_published' => true,
    ]);

    $this->getJson('/api/v1/exams/' . $exam->exam_id . '/enrollments')
        ->assertForbidden();
});
