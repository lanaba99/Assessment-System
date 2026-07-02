<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Tests\Feature\Analytics\UsesAnalyticsSchema;

uses(UsesAnalyticsSchema::class);

beforeEach(function (): void {
    $this->bootGradingSchema();
    $this->migrateAnalyticsTables();
    $this->withoutTenancyIdentificationMiddleware();
    $this->initializeTenantContext($this->tenantA);
});

it('denies viewing the analytics dashboard when the actor lacks analytics.view', function (): void {
    $user = $this->createUser($this->tenantA, password: 'UserPass1!');
    // no permissions granted
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/analytics/dashboard')
        ->assertForbidden();
});

it('denies viewing the analytics dashboard for an unauthenticated request', function (): void {
    $this->getJson('/api/v1/analytics/dashboard')
        ->assertUnauthorized();
});