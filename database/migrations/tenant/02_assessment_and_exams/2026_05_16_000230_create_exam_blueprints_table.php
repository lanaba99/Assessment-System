<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_blueprints', function (Blueprint $table) {
            $table->uuid('blueprint_id')->primary();
            $table->uuid('exam_id');
            $table->uuid('competency_id');

            $table->unsignedInteger('min_questions_count')->default(0);
            $table->unsignedInteger('max_questions_count')->default(0);

            $table->decimal('min_weight_percentage', 5, 2)->default(0);
            $table->decimal('max_weight_percentage', 5, 2)->default(100);

            $table->unsignedInteger('difficulty_distribution_easy_count')->default(0);
            $table->unsignedInteger('difficulty_distribution_medium_count')->default(0);
            $table->unsignedInteger('difficulty_distribution_hard_count')->default(0);

            $table->json('blueprint_metadata')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->foreign('exam_id')
                ->references('exam_id')
                ->on('exams')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->index('competency_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_blueprints');
    }
};
