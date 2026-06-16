<?php

declare(strict_types=1);

use App\Domains\Grading\Models\Grade;
use App\Domains\Grading\Services\FinalGradeProcessingService;
use App\Domains\Penalties\Models\PenaltySanction;
use Tests\Feature\Penalties\UsesPenaltiesSchema;

uses(UsesPenaltiesSchema::class);

beforeEach(function (): void {
    $this->bootGradingSchema();
    $this->withoutTenancyIdentificationMiddleware();
    $this->initializeTenantContext($this->tenantA);
});

it('applies penalty deductions to the final grade when sanctions exist', function (): void {
    ['candidate' => $candidate, 'exam' => $exam, 'session' => $session] = $this->prepareGradingSession();
    $admin = $this->createUser($this->tenantA);

    $rule = $this->createPenaltyRule($this->tenantA, (string) $admin->id, [
        'penalty_points' => 10.0,
    ]);

    $grade = $this->createGrade(
        $this->tenantA,
        (string) $session->session_id,
        (string) $candidate->id,
        (string) $exam->exam_id,
        [
            'normalized_score' => 85.0,
            'final_score' => 85.0,
            'is_final_grade' => true,
            'finalized_at' => now(),
        ],
    );

    $this->createPenaltySanction(
        $this->tenantA,
        (string) $session->session_id,
        (string) $candidate->id,
        (string) $rule->penalty_rule_id,
        10.0,
    );

    app(FinalGradeProcessingService::class)->process(
        $this->tenantA,
        (string) $session->session_id,
    );

    $updated = Grade::query()
        ->where('session_id', $session->session_id)
        ->firstOrFail();

    expect((float) $updated->final_score)->toEqual(75.0)
        ->and($updated->grading_metadata['penalty_deduction'])->toEqual(10.0)
        ->and($updated->grading_metadata['sanctions_applied'])->toHaveCount(1);
});

it('voids a sanction and restores the grade when an override is applied', function (): void {
    ['candidate' => $candidate, 'exam' => $exam, 'session' => $session] = $this->prepareGradingSession();
    $admin = $this->createUser($this->tenantA);

    $rule = $this->createPenaltyRule($this->tenantA, (string) $admin->id, [
        'penalty_points' => 8.0,
    ]);

    $this->createGrade(
        $this->tenantA,
        (string) $session->session_id,
        (string) $candidate->id,
        (string) $exam->exam_id,
        [
            'normalized_score' => 90.0,
            'final_score' => 82.0,
            'is_final_grade' => true,
            'finalized_at' => now(),
            'grading_metadata' => [
                'penalty_deduction' => 8.0,
                'sanctions_applied' => [],
            ],
        ],
    );

    $sanction = $this->createPenaltySanction(
        $this->tenantA,
        (string) $session->session_id,
        (string) $candidate->id,
        (string) $rule->penalty_rule_id,
        8.0,
    );

    app(\App\Domains\Penalties\Services\PenaltySanctionService::class)->voidSanction(
        $this->tenantA,
        (string) $sanction->sanction_id,
        (string) $admin->id,
        'Investigation cleared',
    );

    app(FinalGradeProcessingService::class)->process(
        $this->tenantA,
        (string) $session->session_id,
    );

    $updated = Grade::query()
        ->where('session_id', $session->session_id)
        ->firstOrFail();

    $voided = PenaltySanction::query()->findOrFail($sanction->sanction_id);

    expect($voided->sanction_type)->toBe('voided')
        ->and((float) $updated->final_score)->toEqual(90.0)
        ->and($updated->grading_metadata['penalty_deduction'])->toEqual(0.0);
});
