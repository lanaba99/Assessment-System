<?php

declare(strict_types=1);

use App\Domains\Proctoring\Events\ProctorEventLogged;
use App\Domains\Proctoring\Models\ProctorLog;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Proctoring\UsesProctoringSchema;

uses(UsesProctoringSchema::class);

beforeEach(function (): void {
    $this->bootProctoringSchema();
    $this->withoutTenancyIdentificationMiddleware();
    $this->initializeTenantContext($this->tenantA);

    // ProctorLog::created dispatches ProctorEventLogged, which the Penalties
    // domain listens to (ApplyPenaltyOnProctorEventListener). That listener
    // queries penalty_rules, a table this schema doesn't build — fake the
    // event here to keep these tests scoped to the Proctoring domain.
    Event::fake([ProctorEventLogged::class]);
});

it('ingests a proctoring event when the actor has proctoring.ingest', function (): void {
    $candidate = $this->createUser($this->tenantA, password: 'CandidatePass1!');
    $this->grantPermissionsToUser($candidate, ['proctoring.ingest']);
    Sanctum::actingAs($candidate);

    $exam = $this->createExam($this->tenantA, (string) $candidate->id);
    $enrollment = $this->createEnrollment($this->tenantA, $exam->exam_id, (string) $candidate->id);
    $session = $this->createExamSession(
        $this->tenantA,
        $exam->exam_id,
        $enrollment->enrollment_id,
        (string) $candidate->id,
    );

    $response = $this->postJson("/api/v1/exam-sessions/{$session->session_id}/proctor-events", [
        'event_type' => 'tab_switch',
        'event_timestamp' => now()->toIso8601String(),
        'severity_level' => 'warning',
    ]);

    $response->assertCreated()->assertJsonPath('data.event_type', 'tab_switch');

    expect(ProctorLog::query()->where('session_id', $session->session_id)->exists())->toBeTrue();
});

it('allows a candidate with no proctoring.ingest permission to ingest an event for their own active session', function (): void {
    $candidate = $this->createUser($this->tenantA, password: 'CandidatePass1!');
    // deliberately no permissions granted — proves the ownership path, not the permission path
    Sanctum::actingAs($candidate);

    $exam = $this->createExam($this->tenantA, (string) $candidate->id);
    $enrollment = $this->createEnrollment($this->tenantA, $exam->exam_id, (string) $candidate->id);
    $session = $this->createExamSession(
        $this->tenantA,
        $exam->exam_id,
        $enrollment->enrollment_id,
        (string) $candidate->id,
    );

    $response = $this->postJson("/api/v1/exam-sessions/{$session->session_id}/proctor-events", [
        'event_type' => 'app_backgrounded',
        'event_timestamp' => now()->toIso8601String(),
        'severity_level' => 'warning',
    ]);

    $response->assertCreated()->assertJsonPath('data.event_type', 'app_backgrounded');

    expect(ProctorLog::query()->where('session_id', $session->session_id)->exists())->toBeTrue();
});

it('denies a candidate from ingesting an event for another candidate\'s session', function (): void {
    $owner = $this->createUser($this->tenantA, password: 'OwnerPass1!');
    $intruder = $this->createUser($this->tenantA, password: 'IntruderPass1!');

    $exam = $this->createExam($this->tenantA, (string) $owner->id);
    $enrollment = $this->createEnrollment($this->tenantA, $exam->exam_id, (string) $owner->id);
    $session = $this->createExamSession(
        $this->tenantA,
        $exam->exam_id,
        $enrollment->enrollment_id,
        (string) $owner->id,
    );

    Sanctum::actingAs($intruder);

    $this->postJson("/api/v1/exam-sessions/{$session->session_id}/proctor-events", [
        'event_type' => 'app_backgrounded',
        'event_timestamp' => now()->toIso8601String(),
    ])->assertForbidden();
});

it('denies a candidate from ingesting an event for their own session once it is completed', function (): void {
    $candidate = $this->createUser($this->tenantA, password: 'CandidatePass1!');
    Sanctum::actingAs($candidate);

    $exam = $this->createExam($this->tenantA, (string) $candidate->id);
    $enrollment = $this->createEnrollment($this->tenantA, $exam->exam_id, (string) $candidate->id);
    $session = $this->createExamSession(
        $this->tenantA,
        $exam->exam_id,
        $enrollment->enrollment_id,
        (string) $candidate->id,
        ['session_state' => 'completed'],
    );

    $this->postJson("/api/v1/exam-sessions/{$session->session_id}/proctor-events", [
        'event_type' => 'app_backgrounded',
        'event_timestamp' => now()->toIso8601String(),
    ])->assertForbidden();
});

it('lists proctoring events for a session when the actor has proctoring.view', function (): void {
    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['proctoring.view']);
    Sanctum::actingAs($admin);

    $exam = $this->createExam($this->tenantA, (string) $admin->id);
    $enrollment = $this->createEnrollment($this->tenantA, $exam->exam_id, (string) $admin->id);
    $session = $this->createExamSession(
        $this->tenantA,
        $exam->exam_id,
        $enrollment->enrollment_id,
        (string) $admin->id,
    );
    $this->createProctorLog($this->tenantA, $session->session_id, (string) $admin->id);

    $response = $this->getJson("/api/v1/exam-sessions/{$session->session_id}/proctor-events");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('denies listing proctoring events when the actor lacks proctoring.view', function (): void {
    $user = $this->createUser($this->tenantA, password: 'UserPass1!');
    Sanctum::actingAs($user);

    $exam = $this->createExam($this->tenantA, (string) $user->id);
    $enrollment = $this->createEnrollment($this->tenantA, $exam->exam_id, (string) $user->id);
    $session = $this->createExamSession(
        $this->tenantA,
        $exam->exam_id,
        $enrollment->enrollment_id,
        (string) $user->id,
    );

    $this->getJson("/api/v1/exam-sessions/{$session->session_id}/proctor-events")
        ->assertForbidden();
});

it('returns 404 when ingesting an event for a nonexistent session', function (): void {
    $candidate = $this->createUser($this->tenantA, password: 'CandidatePass1!');
    $this->grantPermissionsToUser($candidate, ['proctoring.ingest']);
    Sanctum::actingAs($candidate);

    $this->postJson('/api/v1/exam-sessions/' . (string) Illuminate\Support\Str::uuid() . '/proctor-events', [
        'event_type' => 'tab_switch',
        'event_timestamp' => now()->toIso8601String(),
    ])->assertNotFound();
});


// notice that this endpoint does not have a tenant-isolation test 
// because the Policy (viewForSession) does not check if the session itself belongs to the same tenant as the actor 
// — it only checks the permission. 
// This is an existing design gap in the code. Keep this in mind for future review.