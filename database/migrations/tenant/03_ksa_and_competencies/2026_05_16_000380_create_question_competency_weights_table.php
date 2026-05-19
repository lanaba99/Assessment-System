<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_competency_weights', function (Blueprint $table) {
            $table->uuid('weight_id')->primary();
            $table->uuid('question_id');
            $table->uuid('competency_id');

            $table->decimal('weight_percentage', 5, 2)->default(0);

            $table->string('skill_category')->nullable();
            $table->string('skill_gap_trigger')->nullable();

            $table->boolean('is_primary_competency')->default(false);

            $table->json('weighting_metadata')->nullable();

            $table->timestamps();

            $table->foreign('question_id')
                ->references('question_id')
                ->on('questions')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('competency_id')
                ->references('competency_id')
                ->on('competencies')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->unique(['question_id', 'competency_id']);
            $table->index('is_primary_competency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_competency_weights');
    }
};
