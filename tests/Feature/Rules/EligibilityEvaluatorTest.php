<?php

declare(strict_types=1);

use App\Domains\Rules\DTOs\EligibilityContext;
use App\Domains\Rules\Services\EligibilityEvaluatorService;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Rules\UsesRulesSchema;

uses(UsesRulesSchema::class);

beforeEach(function (): void {
    $this->bootRulesSchema();
    $this->withoutTenancyIdentificationMiddleware();
    $this->initializeTenantContext($this->tenantA);

    Event::fake([
        \App\Domains\ExamSession\Events\ExamSessionCompleted::class,
        \App\Domains\ExamSession\Events\ResponseSubmitted::class,
    ]);
});

it('passes eligibility when the candidate has a passing prerequisite grade', function (): void {
    $admin = $this->createUser($this->tenantA);
    $candidate = $this->createUser($this->tenantA);

    [$prerequisiteExam] = $this->prepareExamWithMockedItems($this->tenantA, (string) $admin->id);
    [$targetExam] = $this->prepareExamWithMockedItems($this->tenantA, (string) $admin->id);

    $prereqEnrollment = $this->createEnrollment(
        $this->tenantA,
        (string) $prerequisiteExam->exam_id,
        (string) $candidate->id,
    );
    $prereqSession = $this->createExamSession(
        $this->tenantA,
        (string) $prerequisiteExam->exam_id,
        (string) $prereqEnrollment->enrollment_id,
        (string) $candidate->id,
        ['session_state' => 'completed'],
    );

    $this->createPassingGradeForExam(
        $this->tenantA,
        (string) $candidate->id,
        (string) $prerequisiteExam->exam_id,
        (string) $prereqSession->session_id,
        80.0,
    );

    $this->createEligibilityChainStep(
        $this->tenantA,
        (string) $targetExam->exam_id,
        (string) $admin->id,
        1,
        (string) $prerequisiteExam->exam_id,
        70.0,
    );

    $result = app(EligibilityEvaluatorService::class)->evaluate(
        new EligibilityContext($this->tenantA, (string) $candidate->id, (string) $targetExam->exam_id),
    );

    expect($result->isEligible)->toBeTrue();
});

it('fails eligibility when the prerequisite exam was not passed', function (): void {
    $admin = $this->createUser($this->tenantA);
    $candidate = $this->createUser($this->tenantA);

    [$prerequisiteExam] = $this->prepareExamWithMockedItems($this->tenantA, (string) $admin->id);
    [$targetExam] = $this->prepareExamWithMockedItems($this->tenantA, (string) $admin->id);

    $this->createEligibilityChainStep(
        $this->tenantA,
        (string) $targetExam->exam_id,
        (string) $admin->id,
        1,
        (string) $prerequisiteExam->exam_id,
    );

    $result = app(EligibilityEvaluatorService::class)->evaluate(
        new EligibilityContext($this->tenantA, (string) $candidate->id, (string) $targetExam->exam_id),
    );

    expect($result->isEligible)->toBeFalse()
        ->and($result->rejectionReason)->toContain('prerequisite exam');
});

it('blocks session start when eligibility chain requirements are not met', function (): void {
    $admin = $this->createUser($this->tenantA);
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($candidate, ['exam_sessions.start']);
    Sanctum::actingAs($candidate);

    [$prerequisiteExam] = $this->prepareExamWithMockedItems($this->tenantA, (string) $admin->id);
    [$targetExam] = $this->prepareExamWithMockedItems($this->tenantA, (string) $admin->id);

    $this->createEnrollment($this->tenantA, (string) $targetExam->exam_id, (string) $candidate->id);
    $this->createEligibilityChainStep(
        $this->tenantA,
        (string) $targetExam->exam_id,
        (string) $admin->id,
        1,
        (string) $prerequisiteExam->exam_id,
    );

    $this->postJson('/api/v1/exam-sessions/', [
        'exam_id' => (string) $targetExam->exam_id,
    ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'eligibility_violation');
});

it('allows session start when no eligibility chain is configured', function (): void {
    $candidate = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($candidate, ['exam_sessions.start']);
    Sanctum::actingAs($candidate);

    [$exam] = $this->prepareExamWithMockedItems($this->tenantA, (string) $candidate->id);
    $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);

    $this->postJson('/api/v1/exam-sessions/', [
        'exam_id' => (string) $exam->exam_id,
    ])->assertCreated();
});
