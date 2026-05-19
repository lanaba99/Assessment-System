<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rubric_criteria', function (Blueprint $table) {
            $table->uuid('criteria_id')->primary();
            $table->uuid('rubric_id');

            $table->unsignedInteger('criteria_sequence');

            $table->string('criteria_name');
            $table->text('criteria_description')->nullable();

            $table->unsignedTinyInteger('criteria_weight_percentage')->default(0);

            $table->json('scoring_levels')->nullable();
            $table->json('assessment_guidelines')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->foreign('rubric_id')
                ->references('rubric_id')
                ->on('rubrics')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->unique(['rubric_id', 'criteria_sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rubric_criteria');
    }
};
