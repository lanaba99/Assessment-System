<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_responses', function (Blueprint $table) {
            $table->uuid('response_id')->primary();
            $table->uuid('session_id');
            $table->uuid('question_version_id');
            $table->uuid('candidate_user_id');
            $table->uuid('tenant_id');

            $table->unsignedInteger('question_sequence_number');

            $table->string('response_type');
            $table->json('response_data')->nullable();
            $table->text('response_text')->nullable();
            $table->json('selected_options_json')->nullable();
            $table->string('file_upload_url')->nullable();

            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->unsignedInteger('time_elapsed_from_start_seconds')->default(0);

            $table->boolean('is_flagged_for_review')->default(false);
            $table->boolean('is_correct')->nullable();

            $table->decimal('raw_score', 8, 2)->nullable();
            $table->decimal('normalized_score', 8, 2)->nullable();
            $table->decimal('final_score', 8, 2)->nullable();

            $table->json('scoring_metadata')->nullable();

            $table->string('integrity_status')->default('clean');
            $table->json('response_metadata')->nullable();

            $table->timestamp('response_submitted_at')->nullable();
            $table->timestamp('version_lock')->nullable();

            $table->foreign('session_id')
                ->references('session_id')
                ->on('exam_sessions')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('question_version_id')
                ->references('version_id')
                ->on('question_versions')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->foreign('candidate_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->index('tenant_id');
            $table->index('integrity_status');
            $table->unique(['session_id', 'question_sequence_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_responses');
    }
};
