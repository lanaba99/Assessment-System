<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Stancl\Tenancy\Events\TenantCreated;
use Tests\Feature\Identity\UsesIdentitySchema;

trait UsesCentralSchema
{
    use UsesIdentitySchema {
        configureTestDatabaseConnection as configureIdentityTestDatabaseConnection;
    }

    protected function bootCentralSchema(): void
    {
        $this->clearTenantContext();
        $this->configureIdentityTestDatabaseConnection();

        DB::purge((string) config('database.default'));
        DB::reconnect((string) config('database.default'));

        $this->migrateCentralTables();
    }

    private function migrateCentralTables(): void
    {
        $connection = (string) config('database.default');

        if ($connection !== 'sqlite') {
            Schema::connection($connection)->disableForeignKeyConstraints();

            foreach (['personal_access_tokens', 'central_admin_users', 'domains', 'tenants'] as $table) {
                Schema::connection($connection)->dropIfExists($table);
            }

            Schema::connection($connection)->enableForeignKeyConstraints();
        }

        if (! Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('organization_name');
                $table->string('organization_type')->nullable();
                $table->string('primary_contact_email')->nullable();
                $table->string('primary_contact_phone')->nullable();
                $table->json('deployment_config')->nullable();
                $table->string('deployment_mode')->nullable();
                $table->string('data_residency_location')->nullable();
                $table->unsignedInteger('max_concurrent_users')->nullable();
                $table->unsignedBigInteger('max_storage_quota_mb')->nullable();
                $table->json('feature_flags')->nullable();
                $table->string('status')->default('active');
                $table->json('security_policies')->nullable();
                $table->timestamp('contract_start_date')->nullable();
                $table->timestamp('contract_end_date')->nullable();
                $table->timestamp('suspended_at')->nullable();
                $table->timestamps();
                $table->json('data')->nullable();
            });
        }

        if (! Schema::hasTable('domains')) {
            Schema::create('domains', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('domain')->unique();
                $table->string('tenant_id');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('central_admin_users')) {
            Schema::create('central_admin_users', function (Blueprint $table): void {
                $table->uuid('admin_user_id')->primary();
                $table->string('email')->unique();
                $table->string('password_hash');
                $table->string('first_name');
                $table->string('last_name');
                $table->json('admin_permissions')->nullable();
                $table->boolean('is_super_admin')->default(false);
                $table->boolean('mfa_enabled')->default(false);
                $table->json('mfa_settings')->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->string('status')->default('active');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table): void {
                $table->id();
                $table->uuidMorphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }
    }

    protected function createCentralAdmin(string $password = 'ChangeMe123!'): string
    {
        $adminId = (string) Str::uuid();

        DB::table('central_admin_users')->insert([
            'admin_user_id' => $adminId,
            'email' => 'superadmin@central.test',
            'password_hash' => Hash::make($password),
            'first_name' => 'Central',
            'last_name' => 'Admin',
            'admin_permissions' => json_encode(['*']),
            'is_super_admin' => true,
            'status' => 'active',
            'created_at' => now(),
        ]);

        return $adminId;
    }
}
