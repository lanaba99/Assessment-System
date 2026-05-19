<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_policies', function (Blueprint $table) {
            $table->uuid('policy_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('created_by_user_id')->nullable();

            $table->boolean('mfa_enabled')->default(false);
            $table->string('mfa_method')->nullable();

            $table->unsignedInteger('password_min_length')->default(8);
            $table->boolean('password_require_uppercase')->default(true);
            $table->boolean('password_require_lowercase')->default(true);
            $table->boolean('password_require_numbers')->default(true);
            $table->boolean('password_require_special_chars')->default(true);

            $table->unsignedInteger('password_expiry_days')->nullable();
            $table->unsignedInteger('password_history_count')->nullable();

            $table->unsignedInteger('session_timeout_minutes')->nullable();
            $table->unsignedInteger('session_absolute_timeout_hours')->nullable();
            $table->boolean('session_force_reauth_on_privilege_change')->default(false);

            $table->boolean('ip_whitelisting_enabled')->default(false);
            $table->boolean('enable_biometric_auth')->default(false);
            $table->boolean('enforce_tls_1_3_minimum')->default(true);
            $table->boolean('disable_weak_ciphers')->default(true);

            $table->json('allowed_ip_ranges')->nullable();

            $table->timestamp('updated_at')->nullable();

            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_policies');
    }
};
