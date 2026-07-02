<?php

declare(strict_types=1);

use App\Domains\Grading\DTOs\AssessmentSummary;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Grading\UsesGradingSchema;

uses(UsesGradingSchema::class);

beforeEach(function (): void {
    $this->bootGradingSchema();
    $this->withoutTenancyIdentificationMiddleware();
    $this->initializeTenantContext($this->tenantA);
});

it('lets a privileged evaluator view any session result regardless of publication status', function (): void {
    ['candidate' => $candidate, 'exam' => $exam, 'session' => $session] = $this->prepareGradingSession();
    $this->createAssessmentResult(
        $this->tenantA,
        (string) $session->session_id,
        (string) $candidate->id,
        (string) $exam->exam_id,
        ['result_status' => AssessmentSummary::STATUS_FINAL, 'publication_status' => 'unpublished'],
    );

    $evaluator = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($evaluator, ['grading.view']);
    Sanctum::actingAs($evaluator);

    $this->getJson('/api/v1/exam-sessions/' . $session->session_id . '/result')
        ->assertOk()
        ->assertJsonPath('data.session_id', (string) $session->session_id);
});

it('lets the owning candidate view their own result once it is published', function (): void {
    ['candidate' => $candidate, 'exam' => $exam, 'session' => $session] = $this->prepareGradingSession();
    $this->createAssessmentResult(
        $this->tenantA,
        (string) $session->session_id,
        (string) $candidate->id,
        (string) $exam->exam_id,
        ['result_status' => AssessmentSummary::STATUS_FINAL, 'publication_status' => 'published', 'published_at' => now()],
    );

    Sanctum::actingAs($candidate);

    $this->getJson('/api/v1/exam-sessions/' . $session->session_id . '/result')
        ->assertOk()
        ->assertJsonPath('data.session_id', (string) $session->session_id);
});

it('blocks the owning candidate from viewing their own result before it is published', function (): void {
    ['candidate' => $candidate, 'exam' => $exam, 'session' => $session] = $this->prepareGradingSession();
    $this->createAssessmentResult(
        $this->tenantA,
        (string) $session->session_id,
        (string) $candidate->id,
        (string) $exam->exam_id,
        ['result_status' => AssessmentSummary::STATUS_FINAL, 'publication_status' => 'unpublished'],
    );

    Sanctum::actingAs($candidate);

    $this->getJson('/api/v1/exam-sessions/' . $session->session_id . '/result')
        ->assertNotFound();
});

it('blocks an unprivileged user from viewing a different candidate\'s published result', function (): void {
    ['candidate' => $candidate, 'exam' => $exam, 'session' => $session] = $this->prepareGradingSession();
    $this->createAssessmentResult(
        $this->tenantA,
        (string) $session->session_id,
        (string) $candidate->id,
        (string) $exam->exam_id,
        ['result_status' => AssessmentSummary::STATUS_FINAL, 'publication_status' => 'published', 'published_at' => now()],
    );

    // Another plain user in the same tenant, no grading.* permissions, not the candidate.
    $otherCandidate = $this->createUser($this->tenantA);
    Sanctum::actingAs($otherCandidate);

    $this->getJson('/api/v1/exam-sessions/' . $session->session_id . '/result')
        ->assertNotFound();
});

it('rejects unauthenticated requests', function (): void {
    ['candidate' => $candidate, 'exam' => $exam, 'session' => $session] = $this->prepareGradingSession();
    $this->createAssessmentResult(
        $this->tenantA,
        (string) $session->session_id,
        (string) $candidate->id,
        (string) $exam->exam_id,
        ['result_status' => AssessmentSummary::STATUS_FINAL, 'publication_status' => 'published', 'published_at' => now()],
    );

    $this->getJson('/api/v1/exam-sessions/' . $session->session_id . '/result')
        ->assertUnauthorized();
});