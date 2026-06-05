<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_blueprints', function (Blueprint $table): void {
            $table->uuid('blueprint_id')->primary();
            $table->uuid('exam_id');
            $table->uuid('section_id')->nullable();
            $table->uuid('competency_id');

            $table->unsignedInteger('min_questions_count')->default(0);
            $table->unsignedInteger('max_questions_count')->default(0);

            $table->decimal('min_weight_percentage', 5, 2)->default(0);
            $table->decimal('max_weight_percentage', 5, 2)->default(100);

            // Psychometric specification — replaces the old difficulty_distribution_* columns
            // that existed in the pre-consolidation migration history.
            $table->json('bloom_distribution')->nullable();
            $table->decimal('target_difficulty', 4, 3)->default(0.600);
            $table->decimal('min_discrimination', 4, 3)->default(0.200);
            $table->string('resolution_strategy')->default('stratified');

            $table->json('blueprint_metadata')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->foreign('exam_id')
                ->references('exam_id')
                ->on('exams')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('section_id')
                ->references('section_id')
                ->on('exam_sections')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->index('competency_id');
            $table->index('section_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_blueprints');
    }
};
