<?php

declare(strict_types=1);

use App\Domains\Grading\DTOs\AssessmentSummary;
use App\Domains\Grading\Models\Certificate;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Grading\UsesGradingSchema;

uses(UsesGradingSchema::class);

beforeEach(function (): void {
    $this->bootGradingSchema();
    $this->withoutTenancyIdentificationMiddleware();
});

it('does not let tenant A read a certificate belonging to tenant B', function (): void {
    // --- Build the certificate on tenant B ---
    $this->initializeTenantContext($this->tenantB);

    ['candidate' => $candidateB, 'exam' => $examB, 'session' => $sessionB] = $this->prepareGradingSession();

    $resultB = $this->createAssessmentResult(
        $this->tenantB,
        (string) $sessionB->session_id,
        (string) $candidateB->id,
        (string) $examB->exam_id,
        ['result_status' => AssessmentSummary::STATUS_FINAL],
    );

    $certificateB = Certificate::query()->forceCreate([
        'certificate_id' => (string) \Illuminate\Support\Str::uuid(),
        'tenant_id' => $this->tenantB,
        'candidate_user_id' => (string) $candidateB->id,
        'assessment_result_id' => (string) $resultB->result_id,
        'exam_id' => (string) $examB->exam_id,
        'certificate_code' => 'CERT-TENANTB-TEST',
        'issued_at' => now(),
        'verification_status' => 'valid',
    ]);

    // --- Attempt to read it as a tenant-A staff member ---
    $this->initializeTenantContext($this->tenantA);

    $adminA = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($adminA, ['grading.view']);
    Sanctum::actingAs($adminA);

    $this->getJson('/api/v1/certificates/' . $certificateB->certificate_id)
        ->assertForbidden();
});