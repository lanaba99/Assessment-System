<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_gap_mappings', function (Blueprint $table) {
            $table->uuid('mapping_id')->primary();
            $table->uuid('question_id');
            $table->uuid('skill_gap_id');

            $table->string('gap_trigger_condition')->nullable();

            $table->json('recommended_resources')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->foreign('question_id')
                ->references('question_id')
                ->on('questions')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('skill_gap_id')
                ->references('gap_id')
                ->on('skill_gaps')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->unique(['question_id', 'skill_gap_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_gap_mappings');
    }
};
