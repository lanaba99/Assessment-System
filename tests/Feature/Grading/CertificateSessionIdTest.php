<?php

declare(strict_types=1);

use App\Domains\Grading\DTOs\AssessmentSummary;
use App\Domains\Grading\Models\Certificate;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Grading\UsesGradingSchema;

uses(UsesGradingSchema::class);

beforeEach(function (): void {
    $this->bootGradingSchema();
    $this->withoutTenancyIdentificationMiddleware();
    $this->initializeTenantContext($this->tenantA);
});

function makeCertificateForNewSession(string $tenantId): array
{
    /** @var \Tests\TestCase $test */
    $test = test();

    ['candidate' => $candidate, 'exam' => $exam, 'session' => $session] = $test->prepareGradingSession();

    $result = $test->createAssessmentResult(
        $tenantId,
        (string) $session->session_id,
        (string) $candidate->id,
        (string) $exam->exam_id,
        ['result_status' => AssessmentSummary::STATUS_FINAL],
    );

    $certificate = Certificate::query()->forceCreate([
        'certificate_id' => (string) \Illuminate\Support\Str::uuid(),
        'tenant_id' => $tenantId,
        'candidate_user_id' => (string) $candidate->id,
        'assessment_result_id' => (string) $result->result_id,
        'exam_id' => (string) $exam->exam_id,
        'certificate_code' => 'CERT-' . strtoupper(\Illuminate\Support\Str::random(8)),
        'issued_at' => now(),
        'verification_status' => 'valid',
    ]);

    return ['candidate' => $candidate, 'session' => $session, 'certificate' => $certificate];
}

it('includes session_id in the certificate detail response', function (): void {
    ['session' => $session, 'certificate' => $certificate] = makeCertificateForNewSession($this->tenantA);

    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['grading.view']);
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/certificates/' . $certificate->certificate_id)
        ->assertOk()
        ->assertJsonPath('data.session_id', (string) $session->session_id);
});

it('includes session_id in the certificate list response without N+1 queries', function (): void {
    for ($i = 0; $i < 3; $i++) {
        makeCertificateForNewSession($this->tenantA);
    }

    $admin = $this->createUser($this->tenantA, password: 'AdminPass1!');
    $this->grantPermissionsToUser($admin, ['grading.view']);
    Sanctum::actingAs($admin);

    $queryCount = 0;
    DB::listen(function () use (&$queryCount): void {
        $queryCount++;
    });

    $response = $this->getJson('/api/v1/certificates')->assertOk();

    $sessionIds = collect($response->json('data'))->pluck('session_id')->filter()->all();
    expect($sessionIds)->toHaveCount(3);

    // 1 auth/permission lookup(s) aside, the certificate + eager-loaded
    // result should stay constant regardless of row count (no N+1).
    expect($queryCount)->toBeLessThan(10);
});
