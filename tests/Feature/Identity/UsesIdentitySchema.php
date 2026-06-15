<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domains\Identity\Contracts\AuthenticationService;
use App\Domains\Identity\Models\IpWhitelist;
use App\Domains\Identity\Models\LoginAttempt;
use App\Domains\Identity\Models\MfaDevice;
use App\Domains\Identity\Models\SecurityPolicy;
use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserSession;
use Database\Seeders\IdentityPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Stancl\Tenancy\Contracts\Tenant;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/**
 * Test-side support for Identity feature tests.
 *
 * Under production this app is per-database multi-tenant via stancl/tenancy:
 * one tenant_<uuid> database per tenant, with `tenant_id` columns as a
 * defense-in-depth tripwire. For tests we collapse both tenants into a
 * single MySQL testing database (Sail `testing` schema) and rely on the
 * application-layer scope (every repo query is `where('tenant_id', $tid)`)
 * to enforce isolation — that's the layer we want to exercise.
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
        $this->clearTenantContext();
        $this->configureTestDatabaseConnection();
        $this->migrateIdentityTables();

        $this->tenantA = (string) Str::uuid();
        $this->tenantB = (string) Str::uuid();
    }

    /**
     * Honour phpunit.xml / Sail env vars. Prefer MySQL (Sail `testing` DB).
     * Fall back to in-memory SQLite only when MySQL is unavailable and
     * pdo_sqlite is present — e.g. local runs without Sail.
     */
    protected function configureTestDatabaseConnection(): void
    {
        $preferred = (string) env('DB_CONNECTION', 'mysql');

        if ($preferred === 'sqlite' && extension_loaded('pdo_sqlite')) {
            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite' => [
                    'driver' => 'sqlite',
                    'database' => env('DB_DATABASE', ':memory:'),
                    'prefix' => '',
                    'foreign_key_constraints' => true,
                ],
            ]);

            DB::purge('sqlite');
            DB::reconnect('sqlite');

            return;
        }

        $host = env('LARAVEL_SAIL') ? 'mysql' : (string) env('DB_HOST', '127.0.0.1');
        $port = env('LARAVEL_SAIL') ? '3306' : (string) env('DB_PORT', '33060');

        config([
            'database.default' => 'mysql',
            'database.connections.mysql' => array_merge(
                config('database.connections.mysql', []),
                [
                    'driver' => 'mysql',
                    'host' => $host,
                    'port' => $port,
                    'database' => (string) env('DB_DATABASE', 'testing'),
                    'username' => (string) env('DB_USERNAME', 'sail'),
                    'password' => (string) env('DB_PASSWORD', 'password'),
                ],
            ),
        ]);

        DB::purge('mysql');
        DB::reconnect('mysql');
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

        $connection = (string) config('database.default');

        if ($connection !== 'sqlite') {
            $this->resetMysqlTestSchema($connection);
        }

        foreach ($files as $file) {
            $migration = require $file;
            $migration->up();
        }
    }

    /**
     * Wipe the persistent Sail `testing` database before each test case.
     *
     * Migration down() cannot drop identity tables while domain tables still
     * hold FK references to users/roles from the previous test. A full reset
     * keeps MySQL runs as isolated as SQLite :memory:.
     */
    private function resetMysqlTestSchema(string $connection): void
    {
        Schema::connection($connection)->disableForeignKeyConstraints();

        $database = (string) config("database.connections.{$connection}.database");
        $tableKey = 'Tables_in_' . $database;
        $tables = DB::connection($connection)->select('SHOW TABLES');

        foreach ($tables as $table) {
            $tableName = $table->{$tableKey} ?? array_values((array) $table)[0];
            Schema::connection($connection)->drop($tableName);
        }

        Schema::connection($connection)->enableForeignKeyConstraints();
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

    /**
     * Bind a mocked tenant and seed canonical Identity permissions for it.
     * Policies resolve permissions through AuthorizationService, which reads
     * from the permissions / role_permissions tables — seeding here mirrors
     * production `tenants:seed` behaviour.
     */
    protected function initializeTenantContext(string $tenantId): void
    {
        $this->clearTenantContext();

        $tenant = \Mockery::mock(Tenant::class);
        $tenant->shouldReceive('getTenantKey')->andReturn($tenantId);
        $tenant->shouldReceive('getKey')->andReturn($tenantId);

        app()->instance(Tenant::class, $tenant);

        $this->seedIdentityPermissions();
    }

    /**
     * Populate the Permission table with the canonical names checked by domain
     * policies (GradingPolicy, ExamSessionPolicy, etc.). Requires an active
     * tenant() binding — call via initializeTenantContext().
     */
    protected function seedIdentityPermissions(): void
    {
        if (! function_exists('tenant') || tenant() === null) {
            return;
        }

        $this->seed(IdentityPermissionsSeeder::class);
    }

    protected function withoutTenancyIdentificationMiddleware(): void
    {
        $this->withoutMiddleware([
            InitializeTenancyBySubdomain::class,
            PreventAccessFromCentralDomains::class,
        ]);
    }

    protected function clearTenantContext(): void
    {
        if (app()->bound(Tenant::class)) {
            app()->forgetInstance(Tenant::class);
        }

        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }
    }

    /**
     * Assign permissions to a single user via a dedicated test role.
     *
     * Each user receives their own role so that granting permissions to one
     * actor does not clobber another actor's role_permissions sync on the
     * shared per-tenant test role (the previous test-admin-{tenant} pattern).
     *
     * @param  array<int, string>  $permissionNames
     */
    protected function grantPermissionsToUser(User $user, array $permissionNames): void
    {
        $tenantId = (string) $user->tenant_id;
        $permissionIds = [];

        foreach ($permissionNames as $permissionName) {
            $permissionIds[] = (string) $this->resolveTestPermission($tenantId, $permissionName)->permission_id;
        }

        $role = Role::query()->firstOrCreate(
            ['role_name' => "test-role-{$tenantId}-{$user->id}"],
            [
                'role_id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'description' => 'Per-user test role',
                'role_category' => 'admin',
                'is_custom_role' => true,
                'is_system_role' => false,
            ],
        );

        $role->permissions()->sync($permissionIds);
        $user->roles()->sync([(string) $role->role_id]);
    }

    /**
     * Resolve a permission row for AuthorizationService lookups.
     *
     * permission_name is globally unique in the shared test database (mirrors
     * per-tenant DB uniqueness in production). Re-bind tenant context before
     * calling when the active tenant changes so seeded rows carry the correct
     * tenant_id for AuthorizationServiceImpl::listPermissionNamesForUser().
     */
    protected function resolveTestPermission(string $tenantId, string $permissionName): Permission
    {
        [$resource, $action] = array_pad(explode('.', $permissionName, 2), 2, 'access');

        $permission = Permission::query()
            ->where('permission_name', $permissionName)
            ->first();

        if ($permission !== null) {
            if ((string) $permission->tenant_id !== $tenantId) {
                $permission->forceFill(['tenant_id' => $tenantId])->save();
            }

            return $permission;
        }

        return Permission::query()->create([
            'permission_id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'permission_name' => $permissionName,
            'resource_type' => $resource,
            'action_type' => $action,
            'created_at' => now(),
        ]);
    }
}
