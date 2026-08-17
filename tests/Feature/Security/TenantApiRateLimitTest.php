<?php

declare(strict_types=1);

use Tests\Feature\Identity\UsesIdentitySchema;

uses(UsesIdentitySchema::class);

beforeEach(function (): void {
    $this->bootIdentitySchema();
    $this->withoutTenancyIdentificationMiddleware();
    $this->initializeTenantContext($this->tenantA);
});

it('allows requests under the tenant-api rate limit', function (): void {
    // A handful of requests well under the 240/min ceiling should all pass.
    for ($i = 0; $i < 5; $i++) {
        $this->getJson('/api/v1/system/status')->assertOk();
    }
});

it('rate limits a client once it exceeds 240 requests per minute', function (): void {
    for ($i = 0; $i < 240; $i++) {
        $this->getJson('/api/v1/system/status')->assertOk();
    }

    $response = $this->getJson('/api/v1/system/status');

    $response->assertStatus(429);
    $response->assertJsonPath('error.code', 'rate_limited');
    $response->assertHeader('Retry-After');
});

it('keeps rate limit buckets isolated per tenant', function (): void {
    for ($i = 0; $i < 240; $i++) {
        $this->getJson('/api/v1/system/status')->assertOk();
    }
    $this->getJson('/api/v1/system/status')->assertStatus(429);

    // Switching tenant context must use a fresh bucket — tenant B has not
    // made any requests yet, so it should not inherit tenant A's exhaustion.
    $this->initializeTenantContext($this->tenantB);

    $this->getJson('/api/v1/system/status')->assertOk();
});