<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->uuid('grade_id')->primary();
            $table->uuid('session_id');
            $table->uuid('candidate_user_id');
            $table->uuid('exam_id');
            $table->uuid('tenant_id');

            $table->decimal('raw_score', 8, 2)->nullable();
            $table->decimal('weighted_score', 8, 2)->nullable();
            $table->decimal('normalized_score', 8, 2)->nullable();
            $table->decimal('final_score', 8, 2)->nullable();

            $table->string('grade_letter')->nullable();

            $table->boolean('is_passing_grade')->default(false);
            $table->boolean('requires_second_marking')->default(false);
            $table->boolean('is_final_grade')->default(false);

            $table->json('grading_metadata')->nullable();

            $table->timestamp('graded_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('version_lock')->nullable();
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

            $table->foreign('exam_id')
                ->references('exam_id')
                ->on('exams')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->index('tenant_id');
            $table->index('is_passing_grade');
            $table->index('is_final_grade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
