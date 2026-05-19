<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('central_global_settings', function (Blueprint $table) {
            $table->uuid('setting_id')->primary();

            $table->json('ksa_global_template')->nullable();
            $table->json('mandatory_password_policy')->nullable();
            $table->json('mandatory_security_protocols')->nullable();
            $table->json('mandatory_mfa_config')->nullable();
            $table->json('ip_whitelist_global')->nullable();

            $table->unsignedInteger('session_timeout_minutes')->nullable();
            $table->unsignedInteger('max_failed_login_attempts')->nullable();
            $table->unsignedInteger('password_expiry_days')->nullable();

            $table->boolean('enforce_https')->default(true);

            $table->json('backup_schedule')->nullable();

            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('central_global_settings');
    }
};
