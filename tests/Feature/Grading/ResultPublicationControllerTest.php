<?php

declare(strict_types=1);

use App\Domains\Grading\DTOs\AssessmentSummary;
use App\Domains\Grading\Models\AssessmentResult;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Grading\UsesGradingSchema;

uses(UsesGradingSchema::class);

beforeEach(function (): void {
    $this->bootGradingSchema();
    $this->withoutTenancyIdentificationMiddleware();
    $this->initializeTenantContext($this->tenantA);
});

it('publishes a finalized result when the actor has grading.publish permission', function (): void {
    ['candidate' => $candidate, 'exam' => $exam, 'session' => $session] = $this->prepareGradingSession();
    $publisher = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($publisher, ['grading.publish']);
    Sanctum::actingAs($publisher);

    $this->createGrade(
        $this->tenantA,
        (string) $session->session_id,
        (string) $candidate->id,
        (string) $exam->exam_id,
        ['is_final_grade' => true, 'finalized_at' => now()],
    );
    $this->createAssessmentResult(
        $this->tenantA,
        (string) $session->session_id,
        (string) $candidate->id,
        (string) $exam->exam_id,
        ['result_status' => AssessmentSummary::STATUS_FINAL, 'publication_status' => 'unpublished'],
    );

    $this->postJson('/api/v1/exam-sessions/' . $session->session_id . '/result/publish')
        ->assertOk()
        ->assertJsonPath('data.status.publication_status', 'published');

    expect(AssessmentResult::query()
        ->where('session_id', $session->session_id)
        ->value('publication_status'))->toBe('published');
});

it('rejects publication when the actor lacks grading.publish permission', function (): void {
    ['candidate' => $candidate, 'exam' => $exam, 'session' => $session] = $this->prepareGradingSession();
    $viewer = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($viewer, ['grading.view']);
    Sanctum::actingAs($viewer);

    $this->createGrade(
        $this->tenantA,
        (string) $session->session_id,
        (string) $candidate->id,
        (string) $exam->exam_id,
        ['is_final_grade' => true, 'finalized_at' => now()],
    );
    $this->createAssessmentResult(
        $this->tenantA,
        (string) $session->session_id,
        (string) $candidate->id,
        (string) $exam->exam_id,
        ['result_status' => AssessmentSummary::STATUS_FINAL],
    );

    $this->postJson('/api/v1/exam-sessions/' . $session->session_id . '/result/publish')
        ->assertForbidden();
});

it('returns publication status for authorized graders', function (): void {
    ['candidate' => $candidate, 'exam' => $exam, 'session' => $session] = $this->prepareGradingSession();
    $grader = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($grader, ['grading.view']);
    Sanctum::actingAs($grader);

    $this->createAssessmentResult(
        $this->tenantA,
        (string) $session->session_id,
        (string) $candidate->id,
        (string) $exam->exam_id,
        [
            'result_status' => AssessmentSummary::STATUS_FINAL,
            'publication_status' => 'unpublished',
        ],
    );

    $this->getJson('/api/v1/exam-sessions/' . $session->session_id . '/result/publication-status')
        ->assertOk()
        ->assertJsonPath('data.publication_status', 'unpublished')
        ->assertJsonPath('data.result_status', AssessmentSummary::STATUS_FINAL);
});
