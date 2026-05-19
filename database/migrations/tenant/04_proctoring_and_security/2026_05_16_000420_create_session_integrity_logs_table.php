<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_integrity_logs', function (Blueprint $table) {
            $table->uuid('log_id')->primary();
            $table->uuid('session_id');
            $table->uuid('candidate_user_id');
            $table->uuid('tenant_id');

            $table->dateTime('event_timestamp');
            $table->string('event_type');
            $table->json('event_payload')->nullable();

            $table->string('integrity_check_type')->nullable();
            $table->string('integrity_status')->default('clean');
            $table->string('flag_reason')->nullable();
            $table->string('severity_level')->default('info');

            $table->boolean('requires_manual_review')->default(false);

            $table->json('log_metadata')->nullable();

            $table->timestamp('created_at')->nullable();

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
            $table->index('event_type');
            $table->index('integrity_status');
            $table->index('severity_level');
            $table->index('event_timestamp');
            $table->index('requires_manual_review');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_integrity_logs');
    }
};
