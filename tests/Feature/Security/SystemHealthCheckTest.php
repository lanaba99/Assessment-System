<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Tests\Feature\Identity\UsesIdentitySchema;

/**
 * SystemController::status() — the "database" field/behavior already had
 * coverage (IdentityModuleTest, which this file mirrors the tenant-context
 * setup from). These tests cover only the new, additive "redis" field and
 * the overall-status interaction with it.
 */
uses(UsesIdentitySchema::class);

beforeEach(function (): void {
    $this->bootIdentitySchema();
    $this->withoutTenancyIdentificationMiddleware();
    $this->initializeTenantContext($this->tenantA);
});

it('reports redis as not_configured (not degraded) when no redis-backed driver is in use', function (): void {
    Config::set('cache.default', 'array');
    Config::set('queue.default', 'sync');
    Config::set('session.driver', 'array');

    $response = $this->getJson('/api/v1/system/status');

    $response->assertOk();
    $response->assertJsonPath('data.status', 'ok');
    $response->assertJsonPath('data.redis', 'not_configured');
});

it('reports redis as unavailable, and overall status as degraded, when a redis driver is configured but unreachable', function (): void {
    Config::set('cache.default', 'redis');
    // 127.0.0.1 on a port nothing listens on fails fast (connection
    // refused) everywhere, unlike an arbitrary unreachable IP which can hang
    // on OS-specific routing timeouts instead of failing immediately.
    Config::set('database.redis.default.host', '127.0.0.1');
    Config::set('database.redis.default.port', 1);
    Config::set('database.redis.default.timeout', 0.5);

    $response = $this->getJson('/api/v1/system/status');

    $response->assertStatus(503);
    $response->assertJsonPath('data.status', 'degraded');
    $response->assertJsonPath('data.redis', 'unavailable');
});

it('never exposes an exception message or connection details in the health response', function (): void {
    Config::set('cache.default', 'redis');
    Config::set('database.redis.default.host', '127.0.0.1');
    Config::set('database.redis.default.port', 1);
    Config::set('database.redis.default.timeout', 0.5);

    $response = $this->getJson('/api/v1/system/status');

    $body = $response->json();

    expect(array_keys($body['data']))->toEqualCanonicalizing([
        'status', 'tenant_id', 'database', 'redis', 'timestamp',
    ]);
});
