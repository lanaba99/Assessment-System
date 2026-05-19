<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_fingerprints', function (Blueprint $table) {
            $table->uuid('fingerprint_id')->primary();
            $table->uuid('session_id');
            $table->uuid('candidate_user_id');
            $table->uuid('tenant_id');

            $table->string('device_fingerprint_hash');
            $table->string('device_id_hash')->nullable();

            $table->unsignedInteger('screen_resolution_width')->nullable();
            $table->unsignedInteger('screen_resolution_height')->nullable();

            $table->text('browser_user_agent')->nullable();
            $table->string('browser_language')->nullable();
            $table->string('device_timezone')->nullable();

            $table->json('hardware_metadata')->nullable();
            $table->json('software_metadata')->nullable();

            $table->boolean('is_jailbroken_or_rooted')->default(false);
            $table->boolean('is_emulator_detected')->default(false);

            $table->string('fingerprint_verification_status')->default('pending');

            $table->timestamp('captured_at')->nullable();
            $table->timestamp('verified_at')->nullable();

            $table->foreign('session_id')
                ->references('session_id')
                ->on('exam_sessions')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('candidate_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->index('tenant_id');
            $table->index('device_fingerprint_hash');
            $table->index('fingerprint_verification_status');
            $table->index('is_jailbroken_or_rooted');
            $table->index('is_emulator_detected');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_fingerprints');
    }
};
