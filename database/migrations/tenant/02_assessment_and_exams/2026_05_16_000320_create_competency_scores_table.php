<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competency_scores', function (Blueprint $table) {
            $table->uuid('score_id')->primary();
            $table->uuid('candidate_user_id');
            $table->uuid('session_id');
            $table->uuid('competency_id');
            $table->uuid('tenant_id');

            $table->decimal('score_achieved', 8, 2)->nullable();
            $table->decimal('score_target', 8, 2)->nullable();
            $table->decimal('score_maximum', 8, 2)->nullable();

            $table->unsignedTinyInteger('proficiency_level_achieved')->nullable();
            $table->decimal('gap_percentage', 5, 2)->nullable();
            $table->string('gap_status')->nullable();

            $table->json('score_metadata')->nullable();

            $table->timestamp('calculated_at')->nullable();

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

            $table->index('tenant_id');
            $table->index('competency_id');
            $table->index('gap_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competency_scores');
    }
};
