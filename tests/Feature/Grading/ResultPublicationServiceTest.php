<?php

declare(strict_types=1);

use App\Domains\Grading\Contracts\AssessmentResultService;
use App\Domains\Grading\Contracts\ResultPublicationService;
use App\Domains\Grading\DTOs\AssessmentSummary;
use App\Domains\Grading\Exceptions\ResultNotFinalizedException;
use App\Domains\Grading\Models\AssessmentResult;
use Tests\Feature\Grading\UsesGradingSchema;

uses(UsesGradingSchema::class);

beforeEach(function (): void {
    $this->bootGradingSchema();
    $this->withoutTenancyIdentificationMiddleware();
    $this->initializeTenantContext($this->tenantA);
});

it('returns final score and penalty metadata in result summaries', function (): void {
    ['candidate' => $candidate, 'exam' => $exam, 'session' => $session] = $this->prepareGradingSession();

    $this->createGrade(
        $this->tenantA,
        (string) $session->session_id,
        (string) $candidate->id,
        (string) $exam->exam_id,
        [
            'normalized_score' => 92.0,
            'final_score' => 87.0,
            'is_final_grade' => true,
            'finalized_at' => now(),
            'grading_metadata' => [
                'total_evaluations' => 1,
                'pending_evaluations' => 0,
                'correct_count' => 1,
                'incorrect_count' => 0,
                'max_score' => 10.0,
                'breakdown' => [],
                'blueprint_weighted' => true,
                'penalty_deduction' => 5.0,
                'sanctions_applied' => [
                    ['sanction_id' => 'sanction-1', 'amount' => 5.0],
                ],
            ],
        ],
    );
    $this->createAssessmentResult(
        $this->tenantA,
        (string) $session->session_id,
        (string) $candidate->id,
        (string) $exam->exam_id,
        ['publication_status' => 'published', 'published_at' => now()],
    );

    $view = app(AssessmentResultService::class)->getForSession($this->tenantA, (string) $session->session_id);

    expect($view)->not->toBeNull()
        ->and($view->summary?->percentage)->toBe(87.0)
        ->and($view->summary?->weightedScore)->toBe(92.0)
        ->and($view->summary?->penaltyDeduction)->toBe(5.0)
        ->and($view->summary?->sanctionsApplied)->toHaveCount(1);
});

it('hides unpublished results from candidate reads', function (): void {
    ['candidate' => $candidate, 'exam' => $exam, 'session' => $session] = $this->prepareGradingSession();

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
        ['publication_status' => 'unpublished'],
    );

    $view = app(AssessmentResultService::class)->getPublishedForCandidateSession(
        $this->tenantA,
        (string) $session->session_id,
        (string) $candidate->id,
    );

    expect($view)->toBeNull();
});

it('returns published results to the owning candidate only', function (): void {
    ['candidate' => $candidate, 'exam' => $exam, 'session' => $session] = $this->prepareGradingSession();
    $otherCandidate = $this->createUser($this->tenantA);

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
        ['publication_status' => 'published', 'published_at' => now()],
    );

    $service = app(AssessmentResultService::class);

    $ownerView = $service->getPublishedForCandidateSession(
        $this->tenantA,
        (string) $session->session_id,
        (string) $candidate->id,
    );
    $otherView = $service->getPublishedForCandidateSession(
        $this->tenantA,
        (string) $session->session_id,
        (string) $otherCandidate->id,
    );

    expect($ownerView)->not->toBeNull()
        ->and($otherView)->toBeNull();
});

it('does not leak published result reads across tenants', function (): void {
    ['candidate' => $candidate, 'exam' => $exam, 'session' => $session] = $this->prepareGradingSession();

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
        ['publication_status' => 'published', 'published_at' => now()],
    );

    $view = app(AssessmentResultService::class)->getPublishedForCandidateSession(
        $this->tenantB,
        (string) $session->session_id,
        (string) $candidate->id,
    );

    expect($view)->toBeNull();
});

it('publishes finalized results idempotently', function (): void {
    ['candidate' => $candidate, 'exam' => $exam, 'session' => $session] = $this->prepareGradingSession();

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

    $service = app(ResultPublicationService::class);

    $first = $service->publishSessionResult($this->tenantA, (string) $session->session_id);
    $publishedAt = AssessmentResult::query()
        ->where('tenant_id', $this->tenantA)
        ->where('session_id', $session->session_id)
        ->value('published_at');

    $second = $service->publishSessionResult($this->tenantA, (string) $session->session_id);
    $publishedAgainAt = AssessmentResult::query()
        ->where('tenant_id', $this->tenantA)
        ->where('session_id', $session->session_id)
        ->value('published_at');

    expect($first->publicationStatus)->toBe('published')
        ->and($first->publishedAt)->not->toBeNull()
        ->and($second->publicationStatus)->toBe('published')
        ->and((string) $publishedAgainAt)->toBe((string) $publishedAt);
});

it('rejects publishing non-final results', function (): void {
    ['candidate' => $candidate, 'exam' => $exam, 'session' => $session] = $this->prepareGradingSession();

    $this->createGrade(
        $this->tenantA,
        (string) $session->session_id,
        (string) $candidate->id,
        (string) $exam->exam_id,
        ['is_final_grade' => false],
    );
    $this->createAssessmentResult(
        $this->tenantA,
        (string) $session->session_id,
        (string) $candidate->id,
        (string) $exam->exam_id,
        ['result_status' => AssessmentSummary::STATUS_PROVISIONAL],
    );

    expect(fn () => app(ResultPublicationService::class)->publishSessionResult(
        $this->tenantA,
        (string) $session->session_id,
    ))->toThrow(ResultNotFinalizedException::class);
});
