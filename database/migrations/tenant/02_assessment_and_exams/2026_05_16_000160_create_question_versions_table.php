<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_versions', function (Blueprint $table) {
            $table->uuid('version_id')->primary();
            $table->uuid('question_id');
            $table->uuid('created_by_user_id');

            $table->unsignedInteger('ver_num');

            $table->text('question_text');
            $table->string('question_type');
            $table->text('question_stem')->nullable();

            $table->json('options_json')->nullable();
            $table->text('correct_answer_json')->nullable();
            $table->json('explanation_text')->nullable();
            $table->json('evaluator_instructions')->nullable();

            $table->string('approval_status')->default('draft');
            $table->uuid('approved_by_user_id')->nullable();

            $table->unsignedBigInteger('usage_count_in_exams')->default(0);

            $table->string('content_hash')->nullable();
            $table->json('version_metadata')->nullable();

            $table->timestamp('created_at')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->foreign('question_id')
                ->references('question_id')
                ->on('questions')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->foreign('approved_by_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->unique(['question_id', 'ver_num']);
            $table->index('approval_status');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->foreign('current_version_id')
                ->references('version_id')
                ->on('question_versions')
                ->onUpdate('cascade')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });

        Schema::dropIfExists('question_versions');
    }
};
