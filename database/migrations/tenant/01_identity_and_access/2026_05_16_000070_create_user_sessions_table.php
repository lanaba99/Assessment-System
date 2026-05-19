<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->uuid('session_id')->primary();
            $table->uuid('user_id');
            $table->uuid('tenant_id');

            $table->string('device_fingerprint')->unique();
            $table->string('device_id')->nullable();
            $table->string('device_type')->nullable();
            $table->string('browser_name')->nullable();
            $table->string('browser_version')->nullable();
            $table->string('os_name')->nullable();
            $table->string('os_version')->nullable();

            $table->unsignedInteger('screen_width')->nullable();
            $table->unsignedInteger('screen_height')->nullable();

            $table->string('timezone')->nullable();
            $table->string('language_code')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->string('session_state')->default('active');

            $table->timestamp('login_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('logout_at')->nullable();
            $table->timestamp('version')->nullable();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->index('tenant_id');
            $table->index('user_id');
            $table->index('session_state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};
