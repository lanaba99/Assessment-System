<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('behavioral_analytics', function (Blueprint $table) {
            $table->uuid('analytics_id')->primary();
            $table->uuid('session_id');
            $table->uuid('candidate_user_id');
            $table->uuid('tenant_id');

            $table->unsignedInteger('average_response_time_seconds')->nullable();
            $table->unsignedInteger('fastest_response_time_seconds')->nullable();
            $table->unsignedInteger('slowest_response_time_seconds')->nullable();

            $table->decimal('response_time_variance', 10, 4)->nullable();

            $table->unsignedInteger('tab_switch_count')->default(0);
            $table->unsignedInteger('window_blur_count')->default(0);
            $table->unsignedInteger('copy_paste_attempts')->default(0);
            $table->unsignedInteger('right_click_attempts')->default(0);
            $table->unsignedInteger('keyboard_navigation_count')->default(0);
            $table->unsignedInteger('mouse_movement_count')->default(0);

            $table->json('movement_patterns')->nullable();
            $table->json('keystroke_dynamics')->nullable();

            $table->string('behavioral_score')->nullable();
            $table->string('anomaly_detection_result')->nullable();

            $table->json('behavioral_metadata')->nullable();

            $table->timestamp('calculated_at')->nullable();

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

            $table->unique('session_id');
            $table->index('tenant_id');
            $table->index('anomaly_detection_result');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('behavioral_analytics');
    }
};
