<?php

declare(strict_types=1);

use App\Domains\Identity\DTOs\AuthenticationResult;
use App\Domains\Identity\Exceptions\InvalidCredentialsException;
use App\Domains\Identity\Exceptions\IpNotAllowedException;
use App\Domains\Identity\Exceptions\MfaVerificationFailedException;
use App\Domains\Identity\Models\LoginAttempt;
use App\Domains\Identity\Models\UserSession;
use Tests\Feature\Identity\UsesIdentitySchema;

/**
 * The security boundary that matters: identical inputs, two tenants, no leakage.
 *
 * In production the per-database split prevents SQL-level mixing; here we
 * collapse to one in-memory DB to test the application-layer `where tenant_id`
 * scope — the layer that protects against a leaked connection or a
 * misconfigured tenancy bootstrap.
 */
uses(UsesIdentitySchema::class);

beforeEach(function (): void {
    $this->bootIdentitySchema();
});

it('does not find a tenant-A user when authenticating against tenant B', function (): void {
    // Same email, same password — only the tenant differs.
    $email = 'shared.email@example.test';
    $this->createUser($this->tenantA, password: 'secret', overrides: ['email' => $email]);

    // No user with this email exists in tenant B → expect invalid_credentials.
    try {
        $this->authService()->attemptLogin(
            tenantId: $this->tenantB,
            emailOrEmployeeId: $email,
            plaintextPassword: 'secret',
            ipAddress: '127.0.0.1',
            userAgent: 'phpunit',
        );
        $this->fail('Expected InvalidCredentialsException for cross-tenant lookup.');
    } catch (InvalidCredentialsException $e) {
        expect($e->reasonCode)->toBe('invalid_credentials');
    }

    // And the failure should have been recorded under tenant B, NOT tenant A.
    expect($this->loginAttempts($this->tenantA))->toHaveCount(0);
    expect($this->loginAttempts($this->tenantB))->toHaveCount(1);
    expect($this->loginAttempts($this->tenantB)->first()->failure_reason)
        ->toBe('user_not_found');
});

it('does not return a tenant-A user when the auth call carries tenant_id B', function (): void {
    // The strongest expression of this property — same email used in both
    // tenants — would need composite (tenant_id, email) uniqueness to set
    // up; the current migration has a single-column unique on `email`,
    // which works under per-database isolation but blocks the fixture
    // when we collapse both tenants into one in-memory DB for tests. We
    // use distinct emails and still prove the scoping invariant: tenant-B
    // sees zero rows when querying tenant-A's user.
    $userA = $this->createUser($this->tenantA, password: 'secret-a', overrides: ['email' => 'a@example.test']);
    $userB = $this->createUser($this->tenantB, password: 'secret-b', overrides: ['email' => 'b@example.test']);

    // Tenant A finds its own user with its own password.
    $resultA = $this->authService()->attemptLogin(
        tenantId: $this->tenantA,
        emailOrEmployeeId: 'a@example.test',
        plaintextPassword: 'secret-a',
        ipAddress: '127.0.0.1',
        userAgent: 'phpunit',
    );
    expect($resultA->status)->toBe(AuthenticationResult::STATUS_AUTHENTICATED);
    expect($resultA->userId)->toBe($userA->id);

    // Tenant B asking for tenant-A's email finds nothing — even though the
    // row physically exists in this collapsed test DB, the scope predicate
    // hides it.
    expect(fn () => $this->authService()->attemptLogin(
        tenantId: $this->tenantB,
        emailOrEmployeeId: 'a@example.test',
        plaintextPassword: 'secret-a',
        ipAddress: '127.0.0.1',
        userAgent: 'phpunit',
    ))->toThrow(InvalidCredentialsException::class);

    // And the reverse — tenant A cannot see tenant B's user either.
    expect(fn () => $this->authService()->attemptLogin(
        tenantId: $this->tenantA,
        emailOrEmployeeId: 'b@example.test',
        plaintextPassword: 'secret-b',
        ipAddress: '127.0.0.1',
        userAgent: 'phpunit',
    ))->toThrow(InvalidCredentialsException::class);

    // Sanity: tenant B's own login still works with its own creds.
    expect($this->authService()->attemptLogin(
        tenantId: $this->tenantB,
        emailOrEmployeeId: 'b@example.test',
        plaintextPassword: 'secret-b',
        ipAddress: '127.0.0.1',
        userAgent: 'phpunit',
    )->userId)->toBe($userB->id);
});

it('does not revoke tenant-A sessions when revoking for tenant B', function (): void {
    $userA = $this->createUser($this->tenantA);
    $userB = $this->createUser($this->tenantB);

    // Authenticate both — produces an active session in each tenant.
    $this->authService()->attemptLogin(
        tenantId: $this->tenantA,
        emailOrEmployeeId: $userA->email,
        plaintextPassword: 'secret',
        ipAddress: '127.0.0.1',
        userAgent: 'phpunit',
    );

    $this->authService()->attemptLogin(
        tenantId: $this->tenantB,
        emailOrEmployeeId: $userB->email,
        plaintextPassword: 'secret',
        ipAddress: '127.0.0.1',
        userAgent: 'phpunit',
    );

    // Revoking sessions for tenant-B's user must NOT touch tenant-A's session,
    // even if a caller (e.g. a buggy admin endpoint) accidentally passes the
    // wrong user_id against the wrong tenant. The scope is the safety net.
    $revoked = $this->authService()->revokeAllSessionsForUser(
        tenantId: $this->tenantB,
        userId: $userA->id, // ← deliberately mismatched: A's id, B's tenant
    );

    expect($revoked)->toBe(0);
    expect($this->sessions($this->tenantA)->where('session_state', 'active'))->toHaveCount(1);
});

it('treats a tenant-A session id as not-found when logging out from tenant B', function (): void {
    $userA = $this->createUser($this->tenantA);

    $result = $this->authService()->attemptLogin(
        tenantId: $this->tenantA,
        emailOrEmployeeId: $userA->email,
        plaintextPassword: 'secret',
        ipAddress: '127.0.0.1',
        userAgent: 'phpunit',
    );

    // Attempting to logout that session from tenant B must be a no-op,
    // leaving the original session active.
    $this->authService()->logout($this->tenantB, $result->sessionId);

    $session = UserSession::query()->where('session_id', $result->sessionId)->first();
    expect($session)->not->toBeNull();
    expect($session->session_state)->toBe('active');
    expect($session->logout_at)->toBeNull();
});

it('keeps login-attempt audit trails isolated per tenant', function (): void {
    // Generate four failures under tenant A.
    foreach (range(1, 4) as $_) {
        try {
            $this->authService()->attemptLogin(
                tenantId: $this->tenantA,
                emailOrEmployeeId: 'no-such-user@example.test',
                plaintextPassword: 'whatever',
                ipAddress: '127.0.0.1',
                userAgent: 'phpunit',
            );
        } catch (InvalidCredentialsException) {
            // expected
        }
    }

    expect(LoginAttempt::query()->where('tenant_id', $this->tenantA)->count())->toBe(4);
    expect(LoginAttempt::query()->where('tenant_id', $this->tenantB)->count())->toBe(0);
});

it('does not apply tenant-A IP whitelist policy to tenant-B logins', function (): void {
    // Tenant A enforces IP whitelisting — and 10.0.0.5 is NOT on its list.
    $this->createSecurityPolicy($this->tenantA, ['ip_whitelisting_enabled' => true]);
    $this->whitelistIp($this->tenantA, '203.0.113.10');

    // Tenant B has no such policy.
    $this->createSecurityPolicy($this->tenantB, ['ip_whitelisting_enabled' => false]);

    $userA = $this->createUser($this->tenantA);
    $userB = $this->createUser($this->tenantB);

    // Tenant A: blocked.
    expect(fn () => $this->authService()->attemptLogin(
        tenantId: $this->tenantA,
        emailOrEmployeeId: $userA->email,
        plaintextPassword: 'secret',
        ipAddress: '10.0.0.5',
        userAgent: 'phpunit',
    ))->toThrow(IpNotAllowedException::class);

    // Tenant B from the SAME ip: allowed. A's policy must not bleed into B.
    $result = $this->authService()->attemptLogin(
        tenantId: $this->tenantB,
        emailOrEmployeeId: $userB->email,
        plaintextPassword: 'secret',
        ipAddress: '10.0.0.5',
        userAgent: 'phpunit',
    );

    expect($result->status)->toBe(AuthenticationResult::STATUS_AUTHENTICATED);
});

it('does not trigger MFA on tenant-B login when only tenant A has MFA configured', function (): void {
    // The same-email-in-both-tenants framing would express this most
    // strongly but requires composite (tenant_id, email) uniqueness — see
    // the note on the "tenant_id B scopes out tenant-A user" test. We
    // achieve equivalent coverage with distinct emails: the assertion is
    // that A's MFA policy is read from A's tenant_id, not from any
    // ambient state, and therefore does not influence B's login.

    // Tenant A: MFA-on policy + a verified device for its user.
    $this->createSecurityPolicy($this->tenantA, ['mfa_enabled' => true]);
    $userA = $this->createUser($this->tenantA, overrides: ['email' => 'a-mfa-user@example.test']);
    $this->createVerifiedTotpDevice($this->tenantA, $userA->id);

    // Tenant B: MFA off, separate user.
    $this->createSecurityPolicy($this->tenantB, ['mfa_enabled' => false]);
    $userB = $this->createUser($this->tenantB, overrides: ['email' => 'b-mfa-user@example.test']);

    // Tenant A login → MFA required.
    $resultA = $this->authService()->attemptLogin(
        tenantId: $this->tenantA,
        emailOrEmployeeId: 'a-mfa-user@example.test',
        plaintextPassword: 'secret',
        ipAddress: '127.0.0.1',
        userAgent: 'phpunit',
    );
    expect($resultA->status)->toBe(AuthenticationResult::STATUS_MFA_REQUIRED);
    expect($resultA->userId)->toBe($userA->id);

    // Tenant B login → straight through, no MFA, despite A's policy
    // existing in the same physical DB.
    $resultB = $this->authService()->attemptLogin(
        tenantId: $this->tenantB,
        emailOrEmployeeId: 'b-mfa-user@example.test',
        plaintextPassword: 'secret',
        ipAddress: '127.0.0.1',
        userAgent: 'phpunit',
    );
    expect($resultB->status)->toBe(AuthenticationResult::STATUS_AUTHENTICATED);
    expect($resultB->userId)->toBe($userB->id);
});

it('rejects an MFA verification that targets a session from the wrong tenant', function (): void {
    $this->createSecurityPolicy($this->tenantA, ['mfa_enabled' => true]);
    $userA = $this->createUser($this->tenantA);
    $this->createVerifiedTotpDevice($this->tenantA, $userA->id);

    $resultA = $this->authService()->attemptLogin(
        tenantId: $this->tenantA,
        emailOrEmployeeId: $userA->email,
        plaintextPassword: 'secret',
        ipAddress: '127.0.0.1',
        userAgent: 'phpunit',
    );

    expect($resultA->status)->toBe(AuthenticationResult::STATUS_MFA_REQUIRED);

    // Same session id, wrong tenant scope → must be rejected as ineligible,
    // never silently progressed to authenticated.
    expect(fn () => $this->authService()->verifyMfaForSession(
        tenantId: $this->tenantB,
        sessionId: $resultA->sessionId,
        oneTimeCode: '000000',
    ))->toThrow(MfaVerificationFailedException::class);
});
