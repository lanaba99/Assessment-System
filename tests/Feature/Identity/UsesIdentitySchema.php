<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domains\Identity\Contracts\AuthenticationService;
use App\Domains\Identity\Models\IpWhitelist;
use App\Domains\Identity\Models\LoginAttempt;
use App\Domains\Identity\Models\MfaDevice;
use App\Domains\Identity\Models\SecurityPolicy;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Test-side support for Identity feature tests.
 *
 * Under production this app is per-database multi-tenant via stancl/tenancy:
 * one tenant_<uuid> database per tenant, with `tenant_id` columns as a
 * defense-in-depth tripwire. For tests we collapse both tenants into a
 * single in-memory SQLite database and rely on the application-layer scope
 * (every repo query is `where('tenant_id', $tid)`) to enforce isolation —
 * that's the layer we want to exercise. The connection-level separation is
 * provided by the tenancy package and is its problem to test.
 *
 * Implemented as a trait rather than a TestCase subclass because Pest's
 * `pest()->extend(TestCase::class)->in('Feature')` catch-all in Pest.php
 * already binds the parent class for every Feature test; a second
 * `pest()->extend()` for a subdirectory is rejected as a conflict. A trait
 * stacks cleanly on top.
 */
trait UsesIdentitySchema
{
    protected string $tenantA;

    protected string $tenantB;

    /**
     * Pest's Testable trait owns `setUp`, so we can't override it here.
     * Each test file invokes this from `beforeEach` after the Laravel app
     * has already been booted by Pest's setUp.
     */
    protected function bootIdentitySchema(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->migrateIdentityTables();

        $this->tenantA = (string) Str::uuid();
        $this->tenantB = (string) Str::uuid();
    }

    /**
     * Boot only the identity-and-access migrations. We deliberately do NOT
     * run the full migration set — most of the other domain migrations are
     * MySQL-specific or pull in tables irrelevant to identity tests, which
     * makes the suite slow and brittle on SQLite.
     */
    private function migrateIdentityTables(): void
    {
        $files = glob(database_path('migrations/tenant/01_identity_and_access/*.php')) ?: [];
        sort($files);

        foreach ($files as $file) {
            $migration = require $file;
            $migration->up();
        }
    }

    protected function createUser(string $tenantId, string $password = 'secret', array $overrides = []): User
    {
        return User::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'external_employee_id' => 'EMP-' . Str::random(8),
            'email' => 'user-' . Str::random(8) . '@example.test',
            'password_hash' => Hash::make($password),
            'first_name' => 'Test',
            'last_name' => 'User',
            'user_type' => 'examinee',
            'status' => 'active',
            'is_active' => true,
            'activated_at' => now(),
        ], $overrides));
    }

    protected function createSecurityPolicy(string $tenantId, array $overrides = []): SecurityPolicy
    {
        return SecurityPolicy::query()->create(array_merge([
            'tenant_id' => $tenantId,
            'mfa_enabled' => false,
            'ip_whitelisting_enabled' => false,
            'password_min_length' => 8,
            'updated_at' => now(),
        ], $overrides));
    }

    protected function whitelistIp(string $tenantId, string $ipAddress): IpWhitelist
    {
        return IpWhitelist::query()->create([
            'tenant_id' => $tenantId,
            'ip_address' => $ipAddress,
            'is_active' => true,
            'created_at' => now(),
        ]);
    }

    protected function createVerifiedTotpDevice(string $tenantId, string $userId): MfaDevice
    {
        // `device_identifier` is declared NOT NULL by the migration. The
        // production MfaServiceImpl currently writes null there — that's a
        // separate bug to address with the MFA work. For now we pass a real
        // value so this helper doesn't hit the constraint.
        return MfaDevice::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'device_type' => 'totp',
            'device_identifier' => 'test-totp-' . Str::random(8),
            'device_name' => 'Test authenticator',
            'secret_key_hash' => 'encrypted::stub',
            'is_backup_code' => false,
            'is_verified' => true,
            'backup_codes_count' => 0,
            'verified_at' => now(),
            'created_at' => now(),
        ]);
    }

    protected function authService(): AuthenticationService
    {
        return $this->app->make(AuthenticationService::class);
    }

    protected function loginAttempts(string $tenantId): \Illuminate\Database\Eloquent\Collection
    {
        return LoginAttempt::query()->where('tenant_id', $tenantId)->get();
    }

    protected function sessions(string $tenantId): \Illuminate\Database\Eloquent\Collection
    {
        return UserSession::query()->where('tenant_id', $tenantId)->get();
    }
}
