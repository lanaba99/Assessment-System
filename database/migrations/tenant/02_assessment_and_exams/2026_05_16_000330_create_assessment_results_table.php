<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_results', function (Blueprint $table) {
            $table->uuid('result_id')->primary();
            $table->uuid('candidate_user_id');
            $table->uuid('session_id');
            $table->uuid('exam_id');
            $table->uuid('tenant_id');

            $table->string('result_status')->default('pending');

            $table->json('skill_radar_data_json')->nullable();
            $table->json('benchmark_comparison_data')->nullable();

            $table->text('ai_recommendation_text')->nullable();
            $table->decimal('ai_recommendation_confidence', 5, 4)->nullable();

            $table->json('performance_insights')->nullable();
            $table->json('learning_path_recommendations')->nullable();

            $table->dateTime('result_calculated_at')->nullable();

            $table->string('publication_status')->default('unpublished');
            $table->dateTime('published_at')->nullable();

            $table->json('result_metadata')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->foreign('candidate_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('session_id')
                ->references('session_id')
                ->on('exam_sessions')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('exam_id')
                ->references('exam_id')
                ->on('exams')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->index('tenant_id');
            $table->index('result_status');
            $table->index('publication_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_results');
    }
};
