<?php

declare(strict_types=1);

use App\Domains\Grading\Contracts\AssessmentFinalizationService;
use App\Domains\Grading\Events\ResultGenerated;
use App\Domains\Grading\Exceptions\GradeAlreadyFinalizedException;
use App\Domains\Grading\Models\Grade;
use App\Domains\Grading\Repositories\GradeRepository;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Grading\UsesGradingSchema;

uses(UsesGradingSchema::class);

beforeEach(function (): void {
    $this->bootGradingSchema();
    $this->withoutTenancyIdentificationMiddleware();
    $this->initializeTenantContext($this->tenantA);
});

it('throws when modifying finalized grade', function (): void {
    ['candidate' => $candidate, 'exam' => $exam, 'session' => $session] = $this->prepareGradingSession();

    $this->createGrade(
        $this->tenantA,
        (string) $session->session_id,
        (string) $candidate->id,
        (string) $exam->exam_id,
        [
            'is_final_grade' => true,
            'finalized_at' => now(),
            'final_score' => 80.0,
        ],
    );

    $repository = app(GradeRepository::class);
    $summary = $this->buildAssessmentSummary(
        sessionId: (string) $session->session_id,
        candidateId: (string) $candidate->id,
        examId: (string) $exam->exam_id,
        isFinal: true,
        percentage: 90.0,
    );

    expect(fn (): Grade => $repository->upsertFromSummary($summary))
        ->toThrow(GradeAlreadyFinalizedException::class);
});

it('allows modification of provisional grade', function (): void {
    ['candidate' => $candidate, 'exam' => $exam, 'session' => $session] = $this->prepareGradingSession();

    $this->createGrade(
        $this->tenantA,
        (string) $session->session_id,
        (string) $candidate->id,
        (string) $exam->exam_id,
        [
            'is_final_grade' => false,
            'final_score' => 70.0,
        ],
    );

    $repository = app(GradeRepository::class);
    $summary = $this->buildAssessmentSummary(
        sessionId: (string) $session->session_id,
        candidateId: (string) $candidate->id,
        examId: (string) $exam->exam_id,
        isFinal: false,
        percentage: 75.0,
    );

    $updated = $repository->upsertFromSummary($summary);

    expect((float) $updated->final_score)->toBe(75.0)
        ->and($updated->is_final_grade)->toBeFalse();
});

it('finalize returns cached result when already finalized', function (): void {
    ['candidate' => $candidate, 'exam' => $exam, 'session' => $session] = $this->prepareGradingSession();

    $this->createGrade(
        $this->tenantA,
        (string) $session->session_id,
        (string) $candidate->id,
        (string) $exam->exam_id,
        [
            'is_final_grade' => true,
            'finalized_at' => now(),
            'final_score' => 85.0,
            'grade_letter' => 'B',
            'grading_metadata' => [
                'total_evaluations' => 2,
                'pending_evaluations' => 0,
                'correct_count' => 2,
                'incorrect_count' => 0,
                'max_score' => 20.0,
                'breakdown' => [],
                'blueprint_weighted' => false,
                'penalty_deduction' => 0.0,
                'sanctions_applied' => [],
            ],
        ],
    );

    $event = $this->buildExamSessionCompletedEvent(
        sessionId: (string) $session->session_id,
        candidateId: (string) $candidate->id,
        examId: (string) $exam->exam_id,
    );

    Event::fake([ResultGenerated::class]);

    $service = app(AssessmentFinalizationService::class);

    $first = $service->finalize($event);
    $second = $service->finalize($event);

    expect($first->percentage)->toBe(85.0)
        ->and($second->percentage)->toBe(85.0)
        ->and((float) Grade::query()->where('session_id', $session->session_id)->value('final_score'))->toBe(85.0);

    Event::assertNotDispatched(ResultGenerated::class);
});

it('initial finalization works', function (): void {
    ['candidate' => $candidate, 'exam' => $exam, 'session' => $session] = $this->prepareGradingSession();

    $evaluator = $this->createUser($this->tenantA);
    $questionId = $this->createQuestionStub($this->tenantA, (string) $evaluator->id);

    $this->createAnswerEvaluation(
        $this->tenantA,
        (string) $session->session_id,
        $questionId,
        (string) $evaluator->id,
    );

    $event = $this->buildExamSessionCompletedEvent(
        sessionId: (string) $session->session_id,
        candidateId: (string) $candidate->id,
        examId: (string) $exam->exam_id,
    );

    Event::fake([ResultGenerated::class]);

    $summary = app(AssessmentFinalizationService::class)->finalize($event);

    expect($summary->isFinal)->toBeTrue()
        ->and($summary->percentage)->toBe(80.0);

    $grade = Grade::query()
        ->where('tenant_id', $this->tenantA)
        ->where('session_id', $session->session_id)
        ->first();

    expect($grade)->not->toBeNull()
        ->and($grade->is_final_grade)->toBeTrue()
        ->and($grade->finalized_at)->not->toBeNull();

    Event::assertDispatched(ResultGenerated::class, function (ResultGenerated $event): bool {
        return $event->isFirstFinalization === true
            && $event->summary->isFinal === true;
    });
});
