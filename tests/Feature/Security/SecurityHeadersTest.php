<?php

declare(strict_types=1);

/**
 * SecurityHeaders is registered globally (bootstrap/app.php), so any route
 * proves it — /api/ping is used here because it needs no schema/tenancy
 * setup at all, keeping this test purely about the headers.
 */
it('adds baseline security headers to every response', function (): void {
    $response = $this->getJson('/api/ping');

    $response->assertOk();
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('does not send Strict-Transport-Security over a plain HTTP request', function (): void {
    $response = $this->getJson('/api/ping');

    $response->assertOk();
    $response->assertHeaderMissing('Strict-Transport-Security');
});

it('sends Strict-Transport-Security when the request is secure', function (): void {
    $response = $this->getJson('https://localhost/api/ping');

    $response->assertOk();
    $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});
