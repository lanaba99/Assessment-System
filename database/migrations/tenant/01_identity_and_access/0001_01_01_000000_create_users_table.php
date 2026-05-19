<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');

            $table->string('external_employee_id')->nullable()->unique();
            $table->string('email')->unique();
            $table->string('password_hash');

            $table->string('first_name');
            $table->string('last_name');
            $table->string('user_type');

            $table->uuid('department_id')->nullable();

            $table->string('status')->default('pending');
            $table->boolean('is_active')->default(true);

            $table->dateTime('activated_at')->nullable();
            $table->dateTime('deactivated_at')->nullable();

            $table->json('user_attributes')->nullable();

            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();

            $table->timestamps();
            $table->timestamp('last_login_at')->nullable();

            $table->index('tenant_id');
            $table->index('user_type');
            $table->index('status');
            $table->index('department_id');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
