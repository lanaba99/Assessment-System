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
    Event::fake([ProctorEventLogged::class]);
});

it('executes normally when no Idempotency-Key header is sent', function (): void {
    $candidate = $this->createUser($this->tenantA, password: 'CandidatePass1!');
    Sanctum::actingAs($candidate);

    $exam = $this->createExam($this->tenantA, (string) $candidate->id);
    $enrollment = $this->createEnrollment($this->tenantA, $exam->exam_id, (string) $candidate->id);
    $session = $this->createExamSession(
        $this->tenantA,
        $exam->exam_id,
        $enrollment->enrollment_id,
        (string) $candidate->id,
    );

    $payload = [
        'event_type' => 'app_backgrounded',
        'event_timestamp' => now()->toIso8601String(),
        'severity_level' => 'warning',
    ];

    // Two identical requests, no key — both execute, two rows created.
    $this->postJson("/api/v1/exam-sessions/{$session->session_id}/proctor-events", $payload)->assertCreated();
    $this->postJson("/api/v1/exam-sessions/{$session->session_id}/proctor-events", $payload)->assertCreated();

    expect(ProctorLog::query()->where('session_id', $session->session_id)->count())->toBe(2);
});

it('replays the cached response when the same Idempotency-Key and body are reused', function (): void {
    $candidate = $this->createUser($this->tenantA, password: 'CandidatePass1!');
    Sanctum::actingAs($candidate);

    $exam = $this->createExam($this->tenantA, (string) $candidate->id);
    $enrollment = $this->createEnrollment($this->tenantA, $exam->exam_id, (string) $candidate->id);
    $session = $this->createExamSession(
        $this->tenantA,
        $exam->exam_id,
        $enrollment->enrollment_id,
        (string) $candidate->id,
    );

    $payload = [
        'event_type' => 'app_backgrounded',
        'event_timestamp' => now()->toIso8601String(),
        'severity_level' => 'warning',
    ];

    $key = (string) Illuminate\Support\Str::uuid();

    $first = $this->postJson(
        "/api/v1/exam-sessions/{$session->session_id}/proctor-events",
        $payload,
        ['Idempotency-Key' => $key],
    );
    $first->assertCreated();

    $second = $this->postJson(
        "/api/v1/exam-sessions/{$session->session_id}/proctor-events",
        $payload,
        ['Idempotency-Key' => $key],
    );

    $second->assertCreated();
    $second->assertHeader('X-Idempotent-Replay', 'true');
    expect($second->json('data.id'))->toBe($first->json('data.id'));

    // Only one row was ever actually written — the second call replayed
    // the cached response instead of re-executing the handler.
    expect(ProctorLog::query()->where('session_id', $session->session_id)->count())->toBe(1);
});

it('rejects a reused Idempotency-Key when the request body differs', function (): void {
    $candidate = $this->createUser($this->tenantA, password: 'CandidatePass1!');
    Sanctum::actingAs($candidate);

    $exam = $this->createExam($this->tenantA, (string) $candidate->id);
    $enrollment = $this->createEnrollment($this->tenantA, $exam->exam_id, (string) $candidate->id);
    $session = $this->createExamSession(
        $this->tenantA,
        $exam->exam_id,
        $enrollment->enrollment_id,
        (string) $candidate->id,
    );

    $key = (string) Illuminate\Support\Str::uuid();

    $this->postJson(
        "/api/v1/exam-sessions/{$session->session_id}/proctor-events",
        ['event_type' => 'app_backgrounded', 'event_timestamp' => now()->toIso8601String()],
        ['Idempotency-Key' => $key],
    )->assertCreated();

    $conflict = $this->postJson(
        "/api/v1/exam-sessions/{$session->session_id}/proctor-events",
        ['event_type' => 'debugger_detected', 'event_timestamp' => now()->toIso8601String()],
        ['Idempotency-Key' => $key],
    );

    $conflict->assertStatus(409);
    $conflict->assertJsonPath('error.code', 'idempotency_key_reused');
});