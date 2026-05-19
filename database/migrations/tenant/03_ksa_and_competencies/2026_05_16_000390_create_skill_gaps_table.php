<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_gaps', function (Blueprint $table) {
            $table->uuid('gap_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('candidate_user_id');
            $table->uuid('competency_id');

            $table->decimal('current_proficiency_score', 8, 2)->nullable();
            $table->decimal('target_proficiency_score', 8, 2)->nullable();
            $table->decimal('gap_percentage', 5, 2)->nullable();

            $table->string('gap_severity')->nullable();
            $table->string('recommended_training_module')->nullable();
            $table->string('training_status')->default('not_started');

            $table->timestamp('identified_at')->nullable();
            $table->timestamp('training_started_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->foreign('candidate_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('competency_id')
                ->references('competency_id')
                ->on('competencies')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->index('tenant_id');
            $table->index('gap_severity');
            $table->index('training_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_gaps');
    }
};
