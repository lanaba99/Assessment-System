<?php

declare(strict_types=1);

use App\Domains\Analytics\Models\AnalyticsCache;
use App\Domains\Analytics\Services\AnalyticsIngestionService;
use App\Domains\Grading\Events\ResultGenerated;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Analytics\UsesAnalyticsSchema;

uses(UsesAnalyticsSchema::class);

beforeEach(function (): void {
    $this->bootGradingSchema();
    $this->migrateAnalyticsTables();
    $this->withoutTenancyIdentificationMiddleware();
    $this->initializeTenantContext($this->tenantA);
});

it('ingests a ResultGenerated event into the analytics cache', function (): void {
    ['candidate' => $candidate, 'exam' => $exam, 'session' => $session] = $this->prepareGradingSession();

    $summary = $this->buildAssessmentSummary(
        (string) $session->session_id,
        (string) $candidate->id,
        (string) $exam->exam_id,
    );

    $event = new ResultGenerated(
        summary: $summary,
        isFirstFinalization: true,
        calculatedAt: new DateTimeImmutable(),
    );

    app(AnalyticsIngestionService::class)->ingest($event);

    $cache = AnalyticsCache::query()
        ->where('tenant_id', $this->tenantA)
        ->where('cache_key', 'result_finalized:' . $session->session_id)
        ->first();

    expect($cache)->not->toBeNull()
        ->and($cache->cache_value['exam_id'])->toBe((string) $exam->exam_id)
        ->and($cache->cache_value['percentage'])->toBe(80.0);
});

it('dispatches analytics ingestion when a result is generated via the event listener', function (): void {
    Event::fake();

    ['candidate' => $candidate, 'exam' => $exam, 'session' => $session] = $this->prepareGradingSession();

    $summary = $this->buildAssessmentSummary(
        (string) $session->session_id,
        (string) $candidate->id,
        (string) $exam->exam_id,
    );

    Event::dispatch(new ResultGenerated(
        summary: $summary,
        isFirstFinalization: true,
        calculatedAt: new DateTimeImmutable(),
    ));

    Event::assertListening(
        ResultGenerated::class,
        \App\Domains\Analytics\Listeners\IngestResultGeneratedListener::class,
    );
});

it('returns dashboard metrics from the analytics cache', function (): void {
    $admin = $this->createUser($this->tenantA);
    $this->grantPermissionsToUser($admin, ['analytics.view']);
    \Laravel\Sanctum\Sanctum::actingAs($admin);

    AnalyticsCache::query()->forceCreate([
        'cache_id' => (string) \Illuminate\Support\Str::uuid(),
        'tenant_id' => $this->tenantA,
        'cache_key' => 'dashboard:summary',
        'cache_value' => [
            'total_finalized_results' => 3,
            'average_percentage' => 78.5,
        ],
        'computed_at' => now(),
        'expires_at' => now()->addHour(),
    ]);

    $this->getJson('/api/v1/analytics/dashboard')
        ->assertOk()
        ->assertJsonPath('data.total_finalized_results', 3)
        ->assertJsonPath('data.average_percentage', 78.5);
});
