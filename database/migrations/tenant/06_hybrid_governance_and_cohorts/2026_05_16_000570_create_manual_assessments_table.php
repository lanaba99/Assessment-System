<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_assessments', function (Blueprint $table) {
            $table->uuid('assessment_id')->primary();
            $table->uuid('exam_id');
            $table->uuid('evaluator_user_id');
            $table->uuid('candidate_user_id');
            $table->uuid('tenant_id');
            $table->uuid('correlated_session_id')->nullable();

            $table->string('assessment_type');
            $table->string('assessment_mode');

            $table->json('assessment_data_json')->nullable();

            $table->dateTime('assessment_conducted_at')->nullable();

            $table->string('assessment_status')->default('draft');

            $table->json('assessment_metadata')->nullable();

            $table->timestamps();

            $table->foreign('exam_id')
                ->references('exam_id')
                ->on('exams')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->foreign('evaluator_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->foreign('candidate_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('correlated_session_id')
                ->references('session_id')
                ->on('exam_sessions')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->index('tenant_id');
            $table->index('assessment_type');
            $table->index('assessment_mode');
            $table->index('assessment_status');
            $table->index('assessment_conducted_at');
            $table->index(['exam_id', 'assessment_status']);
            $table->index(['candidate_user_id', 'assessment_status']);
            $table->index(['evaluator_user_id', 'assessment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_assessments');
    }
};
