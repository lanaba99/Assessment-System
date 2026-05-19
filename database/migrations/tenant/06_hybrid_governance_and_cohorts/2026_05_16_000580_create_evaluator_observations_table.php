<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluator_observations', function (Blueprint $table) {
            $table->uuid('observation_id')->primary();
            $table->uuid('manual_assessment_id');
            $table->uuid('evaluator_user_id');
            $table->uuid('tenant_id');

            $table->string('observation_category');
            $table->json('observation_data')->nullable();

            $table->string('severity_level')->default('info');

            $table->boolean('affects_final_score')->default(false);

            $table->string('observation_status')->default('pending');

            $table->json('observation_metadata')->nullable();

            $table->timestamp('observed_at')->nullable();
            $table->timestamps();

            $table->foreign('manual_assessment_id')
                ->references('assessment_id')
                ->on('manual_assessments')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('evaluator_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->index('tenant_id');
            $table->index('observation_category');
            $table->index('severity_level');
            $table->index('observation_status');
            $table->index('affects_final_score');
            $table->index(['manual_assessment_id', 'severity_level'], 'eval_obs_assessment_severity_idx');
            $table->index(['manual_assessment_id', 'observation_status'], 'eval_obs_assessment_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluator_observations');
    }
};
