<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proctoring_events', function (Blueprint $table) {
            $table->uuid('event_id')->primary();
            $table->uuid('session_id');
            $table->uuid('candidate_user_id');
            $table->uuid('tenant_id');
            $table->uuid('reviewing_proctor_id')->nullable();

            $table->dateTime('event_timestamp');
            $table->string('event_type');
            $table->string('event_category')->nullable();

            $table->json('event_payload')->nullable();
            $table->json('detection_parameters')->nullable();

            $table->string('severity_level')->default('info');
            $table->decimal('detection_confidence_score', 5, 4)->nullable();

            $table->string('screenshot_url')->nullable();
            $table->string('video_segment_url')->nullable();

            $table->boolean('requires_investigation')->default(false);
            $table->boolean('is_escalated')->default(false);

            $table->string('investigation_status')->default('open');
            $table->json('investigation_notes')->nullable();

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

            $table->foreign('reviewing_proctor_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->index('tenant_id');
            $table->index('event_type');
            $table->index('event_category');
            $table->index('severity_level');
            $table->index('investigation_status');
            $table->index('event_timestamp');
            $table->index('requires_investigation');
            $table->index('is_escalated');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proctoring_events');
    }
};
