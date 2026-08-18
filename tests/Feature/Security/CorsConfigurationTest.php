<?php

declare(strict_types=1);

/**
 * config/cors.php reads CORS_ALLOWED_ORIGINS at config-load time, so tests
 * override it via Config::set (the config cache is already warm by the time
 * a test runs) rather than env vars, which wouldn't be re-read.
 */

use Illuminate\Support\Facades\Config;

it('adds Access-Control-Allow-Origin for an explicitly allowed origin', function (): void {
    Config::set('cors.allowed_origins', ['https://app.example.com']);

    $response = $this->withHeaders([
        'Origin' => 'https://app.example.com',
    ])->getJson('/api/ping');

    $response->assertOk();
    $response->assertHeader('Access-Control-Allow-Origin', 'https://app.example.com');
});

it('does not add Access-Control-Allow-Origin for a disallowed origin', function (): void {
    // Two configured origins on purpose (matching the realistic
    // CORS_ALLOWED_ORIGINS=prod,staging shape): with exactly one origin
    // configured, the underlying CORS library takes a "safe single origin"
    // shortcut and always echoes that one value back regardless of the
    // request's actual Origin header — still secure (a mismatched value
    // means the requesting browser rejects the response itself), but it
    // means the header is always present with one origin. With two or more
    // configured, the library falls back to real per-request origin
    // matching, which is what this test needs to exercise.
    Config::set('cors.allowed_origins', ['https://app.example.com', 'https://staging.example.com']);

    $response = $this->withHeaders([
        'Origin' => 'https://attacker.example.net',
    ])->getJson('/api/ping');

    $response->assertOk();
    $response->assertHeaderMissing('Access-Control-Allow-Origin');
});

it('allows no origins at all by default (CORS_ALLOWED_ORIGINS unset)', function (): void {
    Config::set('cors.allowed_origins', []);

    $response = $this->withHeaders([
        'Origin' => 'https://anything.example.com',
    ])->getJson('/api/ping');

    $response->assertOk();
    $response->assertHeaderMissing('Access-Control-Allow-Origin');
});

it('never falls back to a wildcard origin', function (): void {
    expect(config('cors.allowed_origins'))->not->toContain('*');
});

it('does not enable credentialed CORS (this API is bearer-token authenticated, not cookie-based)', function (): void {
    expect(config('cors.supports_credentials'))->toBeFalse();
});
