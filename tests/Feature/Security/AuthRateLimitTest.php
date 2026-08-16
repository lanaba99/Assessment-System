<?php

declare(strict_types=1);

use Tests\Feature\Identity\UsesIdentitySchema;

uses(UsesIdentitySchema::class);

beforeEach(function (): void {
    $this->bootIdentitySchema();
    $this->withoutTenancyIdentificationMiddleware();
    $this->initializeTenantContext($this->tenantA);
    $this->createSecurityPolicy($this->tenantA, [
        'password_min_length' => 8,
        'password_require_uppercase' => true,
        'password_require_lowercase' => true,
        'password_require_numbers' => true,
        'password_require_special_chars' => true,
    ]);
});

it('rate limits repeated MFA verification attempts', function (): void {
    $payload = [
        'session_id' => (string) \Illuminate\Support\Str::uuid(), // valid shape, never matches a real MFA session
        'one_time_code' => '000000',
    ];

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/mfa/verify', $payload)->assertUnauthorized();
    }

    $this->postJson('/api/v1/auth/mfa/verify', $payload)
        ->assertStatus(429);
});

it('rate limits repeated password reset attempts', function (): void {
    $payload = [
        'email' => 'nobody@example.test',
        'token' => str_repeat('a', 32), // passes min:16 validation, never a real token
        'password' => 'SecurePass1!',
        'password_confirmation' => 'SecurePass1!',
    ];

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/password/reset', $payload)->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/password/reset', $payload)
        ->assertStatus(429);
});

it('rate limits repeated accept-invite attempts', function (): void {
    $payload = [
        'email' => 'nobody@example.test',
        'token' => str_repeat('a', 32), // passes min:32 validation, never a real invite token
        'password' => 'SecurePass1!',
        'password_confirmation' => 'SecurePass1!',
    ];

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/accept-invite', $payload)->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/accept-invite', $payload)
        ->assertStatus(429);
});