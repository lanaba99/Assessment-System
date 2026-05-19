<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('answer_evaluations', function (Blueprint $table) {
            $table->uuid('evaluation_id')->primary();
            $table->uuid('session_id');
            $table->uuid('question_id');
            $table->uuid('evaluator_user_id');
            $table->uuid('tenant_id');
            $table->uuid('rubric_id')->nullable();

            $table->string('evaluation_type');
            $table->json('rubric_criteria_json')->nullable();

            $table->decimal('score_awarded', 8, 2)->nullable();
            $table->decimal('max_score_possible', 8, 2)->nullable();

            $table->string('evaluation_status')->default('pending');

            $table->json('evaluator_comments')->nullable();
            $table->json('evaluation_metadata')->nullable();

            $table->boolean('requires_secondary_review')->default(false);
            $table->uuid('secondary_reviewer_id')->nullable();

            $table->timestamp('evaluated_at')->nullable();
            $table->timestamp('secondary_reviewed_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('session_id')
                ->references('session_id')
                ->on('exam_sessions')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('question_id')
                ->references('question_id')
                ->on('questions')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->foreign('evaluator_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->foreign('rubric_id')
                ->references('rubric_id')
                ->on('rubrics')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->foreign('secondary_reviewer_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->index('tenant_id');
            $table->index('evaluation_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answer_evaluations');
    }
};
