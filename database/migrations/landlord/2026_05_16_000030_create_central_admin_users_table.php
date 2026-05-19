<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('central_admin_users', function (Blueprint $table) {
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

            $table->index('status');
            $table->index('is_super_admin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('central_admin_users');
    }
};
