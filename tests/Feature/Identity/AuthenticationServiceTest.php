<?php

declare(strict_types=1);

use App\Domains\Identity\Contracts\MfaService;
use App\Domains\Identity\DTOs\AuthenticationResult;
use App\Domains\Identity\Exceptions\InvalidCredentialsException;
use App\Domains\Identity\Exceptions\IpNotAllowedException;
use App\Domains\Identity\Exceptions\MfaVerificationFailedException;
use App\Domains\Identity\Exceptions\UserInactiveException;
use App\Domains\Identity\Models\LoginAttempt;
use App\Domains\Identity\Models\UserSession;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Identity\UsesIdentitySchema;

uses(UsesIdentitySchema::class);

beforeEach(function (): void {
    $this->bootIdentitySchema();
});

it('authenticates a valid email + password and opens an active session', function (): void {
    $user = $this->createUser($this->tenantA, password: 'p@ssw0rd');

    $result = $this->authService()->attemptLogin(
        tenantId: $this->tenantA,
        emailOrEmployeeId: $user->email,
        plaintextPassword: 'p@ssw0rd',
        ipAddress: '127.0.0.1',
        userAgent: 'phpunit',
    );

    expect($result->status)->toBe(AuthenticationResult::STATUS_AUTHENTICATED);
    expect($result->userId)->toBe($user->id);
    expect($result->mfaRequired)->toBeFalse();
    expect($result->authenticatedAt)->not->toBeNull();

    $session = UserSession::query()->whereKey($result->sessionId)->first();
    expect($session->session_state)->toBe('active');
    expect($session->tenant_id)->toBe($this->tenantA);
    expect($session->user_id)->toBe($user->id);

    $attempts = LoginAttempt::query()->where('tenant_id', $this->tenantA)->get();
    expect($attempts)->toHaveCount(1);
    expect($attempts->first()->is_successful)->toBeTrue();

    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('authenticates by external_employee_id when no matching email is found', function (): void {
    $user = $this->createUser($this->tenantA, overrides: ['external_employee_id' => 'EMP-1234']);

    $result = $this->authService()->attemptLogin(
        tenantId: $this->tenantA,
        emailOrEmployeeId: 'EMP-1234',
        plaintextPassword: 'secret',
        ipAddress: '127.0.0.1',
        userAgent: 'phpunit',
    );

    expect($result->status)->toBe(AuthenticationResult::STATUS_AUTHENTICATED);
    expect($result->userId)->toBe($user->id);
});

it('rejects an unknown email and records a user_not_found failure', function (): void {
    expect(fn () => $this->authService()->attemptLogin(
        tenantId: $this->tenantA,
        emailOrEmployeeId: 'nobody@example.test',
        plaintextPassword: 'whatever',
        ipAddress: '127.0.0.1',
        userAgent: 'phpunit',
    ))->toThrow(InvalidCredentialsException::class);

    $attempt = LoginAttempt::query()->where('tenant_id', $this->tenantA)->first();
    expect($attempt)->not->toBeNull();
    expect($attempt->is_successful)->toBeFalse();
    expect($attempt->failure_reason)->toBe('user_not_found');
});

it('rejects a wrong password and records an invalid_password failure', function (): void {
    $user = $this->createUser($this->tenantA, password: 'correct-pw');

    expect(fn () => $this->authService()->attemptLogin(
        tenantId: $this->tenantA,
        emailOrEmployeeId: $user->email,
        plaintextPassword: 'WRONG',
        ipAddress: '127.0.0.1',
        userAgent: 'phpunit',
    ))->toThrow(InvalidCredentialsException::class);

    $attempt = LoginAttempt::query()->where('tenant_id', $this->tenantA)->first();
    expect($attempt->failure_reason)->toBe('invalid_password');
    expect($attempt->is_successful)->toBeFalse();
});

it('rejects an inactive user with the correct reason code', function (): void {
    $user = $this->createUser($this->tenantA, overrides: [
        'is_active' => false,
        'status' => 'deactivated',
        'deactivated_at' => now(),
    ]);

    try {
        $this->authService()->attemptLogin(
            tenantId: $this->tenantA,
            emailOrEmployeeId: $user->email,
            plaintextPassword: 'secret',
            ipAddress: '127.0.0.1',
            userAgent: 'phpunit',
        );
        $this->fail('Expected UserInactiveException.');
    } catch (UserInactiveException $e) {
        expect($e->reasonCode)->toBe('user_inactive');
    }

    expect(LoginAttempt::query()->where('failure_reason', 'user_inactive')->count())->toBe(1);
});

it('blocks logins when IP whitelisting is on and the IP is not on the list', function (): void {
    $this->createSecurityPolicy($this->tenantA, ['ip_whitelisting_enabled' => true]);
    $this->whitelistIp($this->tenantA, '203.0.113.10');
    $user = $this->createUser($this->tenantA);

    expect(fn () => $this->authService()->attemptLogin(
        tenantId: $this->tenantA,
        emailOrEmployeeId: $user->email,
        plaintextPassword: 'secret',
        ipAddress: '198.51.100.7',
        userAgent: 'phpunit',
    ))->toThrow(IpNotAllowedException::class);

    expect(LoginAttempt::query()->where('failure_reason', 'ip_not_allowed')->count())->toBe(1);
});

it('allows logins from whitelisted IPs when whitelisting is on', function (): void {
    $this->createSecurityPolicy($this->tenantA, ['ip_whitelisting_enabled' => true]);
    $this->whitelistIp($this->tenantA, '203.0.113.10');
    $user = $this->createUser($this->tenantA);

    $result = $this->authService()->attemptLogin(
        tenantId: $this->tenantA,
        emailOrEmployeeId: $user->email,
        plaintextPassword: 'secret',
        ipAddress: '203.0.113.10',
        userAgent: 'phpunit',
    );

    expect($result->status)->toBe(AuthenticationResult::STATUS_AUTHENTICATED);
});

it('challenges with MFA when policy requires it and the user has a verified device', function (): void {
    $this->createSecurityPolicy($this->tenantA, ['mfa_enabled' => true]);
    $user = $this->createUser($this->tenantA);
    $this->createVerifiedTotpDevice($this->tenantA, $user->id);

    $result = $this->authService()->attemptLogin(
        tenantId: $this->tenantA,
        emailOrEmployeeId: $user->email,
        plaintextPassword: 'secret',
        ipAddress: '127.0.0.1',
        userAgent: 'phpunit',
    );

    expect($result->status)->toBe(AuthenticationResult::STATUS_MFA_REQUIRED);
    expect($result->mfaRequired)->toBeTrue();
    expect($result->authenticatedAt)->toBeNull();

    $session = UserSession::query()->whereKey($result->sessionId)->first();
    expect($session->session_state)->toBe('pending_mfa');
});

it('skips MFA when policy requires it but the user has no verified device', function (): void {
    // Policy says MFA on, but if the user never enrolled, we cannot enforce it.
    // The service treats this as no-challenge — onboarding is a separate flow.
    $this->createSecurityPolicy($this->tenantA, ['mfa_enabled' => true]);
    $user = $this->createUser($this->tenantA);

    $result = $this->authService()->attemptLogin(
        tenantId: $this->tenantA,
        emailOrEmployeeId: $user->email,
        plaintextPassword: 'secret',
        ipAddress: '127.0.0.1',
        userAgent: 'phpunit',
    );

    expect($result->status)->toBe(AuthenticationResult::STATUS_AUTHENTICATED);
});

it('promotes a pending_mfa session to active when the MFA code verifies', function (): void {
    $this->createSecurityPolicy($this->tenantA, ['mfa_enabled' => true]);
    $user = $this->createUser($this->tenantA);
    $this->createVerifiedTotpDevice($this->tenantA, $user->id);

    // Bypass real TOTP math — the contract guarantees a bool, that's all we
    // care about here. Real TOTP verification is covered by MfaService tests.
    $this->mock(MfaService::class, function ($mock) {
        $mock->shouldReceive('verifyToken')->andReturnTrue();
    });

    $login = $this->authService()->attemptLogin(
        tenantId: $this->tenantA,
        emailOrEmployeeId: $user->email,
        plaintextPassword: 'secret',
        ipAddress: '127.0.0.1',
        userAgent: 'phpunit',
    );

    $verified = $this->authService()->verifyMfaForSession(
        tenantId: $this->tenantA,
        sessionId: $login->sessionId,
        oneTimeCode: '123456',
    );

    expect($verified->status)->toBe(AuthenticationResult::STATUS_AUTHENTICATED);
    expect(UserSession::query()->whereKey($login->sessionId)->first()->session_state)
        ->toBe('active');
});

it('rejects MFA verification when the code is invalid', function (): void {
    $this->createSecurityPolicy($this->tenantA, ['mfa_enabled' => true]);
    $user = $this->createUser($this->tenantA);
    $this->createVerifiedTotpDevice($this->tenantA, $user->id);

    $this->mock(MfaService::class, function ($mock) {
        $mock->shouldReceive('verifyToken')->andReturnFalse();
    });

    $login = $this->authService()->attemptLogin(
        tenantId: $this->tenantA,
        emailOrEmployeeId: $user->email,
        plaintextPassword: 'secret',
        ipAddress: '127.0.0.1',
        userAgent: 'phpunit',
    );

    expect(fn () => $this->authService()->verifyMfaForSession(
        tenantId: $this->tenantA,
        sessionId: $login->sessionId,
        oneTimeCode: 'WRONG',
    ))->toThrow(MfaVerificationFailedException::class);

    // Session must remain pending_mfa — never silently promoted.
    expect(UserSession::query()->whereKey($login->sessionId)->first()->session_state)
        ->toBe('pending_mfa');
});

it('refuses MFA verification on a session that is not awaiting MFA', function (): void {
    $user = $this->createUser($this->tenantA);

    // No MFA policy → session lands as active immediately.
    $login = $this->authService()->attemptLogin(
        tenantId: $this->tenantA,
        emailOrEmployeeId: $user->email,
        plaintextPassword: 'secret',
        ipAddress: '127.0.0.1',
        userAgent: 'phpunit',
    );

    expect($login->status)->toBe(AuthenticationResult::STATUS_AUTHENTICATED);

    try {
        $this->authService()->verifyMfaForSession(
            tenantId: $this->tenantA,
            sessionId: $login->sessionId,
            oneTimeCode: '123456',
        );
        $this->fail('Expected MfaVerificationFailedException.');
    } catch (MfaVerificationFailedException $e) {
        expect($e->reasonCode)->toBe('mfa_session_not_eligible');
    }
});

it('rehashes the password when the stored hash needs upgrading', function (): void {
    // Store a hash made with weaker cost than the current default — the
    // hasher will report needsRehash=true and the service should silently
    // upgrade the stored hash on a successful login.
    $weak = password_hash('secret', PASSWORD_BCRYPT, ['cost' => 4]);
    $user = $this->createUser($this->tenantA, overrides: ['password_hash' => $weak]);

    // Force the hasher to consider the stored hash stale.
    $hasher = \Mockery::mock(Hasher::class);
    $hasher->shouldReceive('check')->andReturnTrue();
    $hasher->shouldReceive('needsRehash')->andReturnTrue();
    $hasher->shouldReceive('make')->andReturn('rehashed::value');
    $this->app->instance(Hasher::class, $hasher);

    $this->authService()->attemptLogin(
        tenantId: $this->tenantA,
        emailOrEmployeeId: $user->email,
        plaintextPassword: 'secret',
        ipAddress: '127.0.0.1',
        userAgent: 'phpunit',
    );

    expect($user->fresh()->password_hash)->toBe('rehashed::value');
});

it('closes a session on logout and is idempotent on a missing session', function (): void {
    $user = $this->createUser($this->tenantA);
    $login = $this->authService()->attemptLogin(
        tenantId: $this->tenantA,
        emailOrEmployeeId: $user->email,
        plaintextPassword: 'secret',
        ipAddress: '127.0.0.1',
        userAgent: 'phpunit',
    );

    $this->authService()->logout($this->tenantA, $login->sessionId);

    $session = UserSession::query()->whereKey($login->sessionId)->first();
    expect($session->session_state)->toBe('closed');
    expect($session->logout_at)->not->toBeNull();

    // A second logout for an unknown session id must not throw — silent no-op.
    $this->authService()->logout($this->tenantA, '00000000-0000-0000-0000-000000000000');
});

it('revokes all active sessions for a user in one call', function (): void {
    $user = $this->createUser($this->tenantA);

    foreach (range(1, 3) as $_) {
        $this->authService()->attemptLogin(
            tenantId: $this->tenantA,
            emailOrEmployeeId: $user->email,
            plaintextPassword: 'secret',
            ipAddress: '127.0.0.1',
            userAgent: 'phpunit',
        );
    }

    $revoked = $this->authService()->revokeAllSessionsForUser($this->tenantA, $user->id);

    expect($revoked)->toBe(3);
    expect(UserSession::query()
        ->where('user_id', $user->id)
        ->whereNull('logout_at')
        ->count())->toBe(0);
});
