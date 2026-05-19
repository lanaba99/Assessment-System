<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_sessions', function (Blueprint $table) {
            $table->uuid('session_id')->primary();
            $table->uuid('exam_id');
            $table->uuid('enrollment_id');
            $table->uuid('candidate_user_id');
            $table->uuid('tenant_id');
            $table->uuid('proctor_user_id')->nullable();

            $table->string('session_state')->default('not_started');

            $table->string('current_question_reference')->nullable();
            $table->unsignedInteger('current_question_index')->default(0);
            $table->unsignedInteger('total_questions_responded')->default(0);
            $table->unsignedInteger('total_questions_flagged')->default(0);

            $table->json('session_progress_json')->nullable();
            $table->json('candidate_device_metadata')->nullable();

            $table->string('device_fingerprint')->nullable();
            $table->string('device_id')->nullable();
            $table->string('device_type')->nullable();
            $table->string('browser_type')->nullable();
            $table->string('operating_system')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('initial_ip_address', 45)->nullable();

            $table->decimal('gps_latitude', 10, 7)->nullable();
            $table->decimal('gps_longitude', 10, 7)->nullable();
            $table->string('session_start_location')->nullable();

            $table->dateTime('session_started_at')->nullable();
            $table->dateTime('session_resumed_at')->nullable();
            $table->dateTime('session_ended_at')->nullable();

            $table->unsignedInteger('total_session_duration_seconds')->default(0);
            $table->unsignedInteger('actual_response_time_seconds')->default(0);

            $table->string('completion_method')->nullable();

            $table->timestamp('version_lock')->nullable();
            $table->timestamps();

            $table->foreign('exam_id')
                ->references('exam_id')
                ->on('exams')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->foreign('enrollment_id')
                ->references('enrollment_id')
                ->on('exam_enrollments')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('candidate_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('proctor_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->index('tenant_id');
            $table->index('session_state');
            $table->index('session_started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_sessions');
    }
};
