<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competency_levels', function (Blueprint $table) {
            $table->uuid('level_id')->primary();
            $table->uuid('competency_id');

            $table->unsignedTinyInteger('level_number');

            $table->string('level_name');
            $table->text('level_description')->nullable();

            $table->decimal('min_score_threshold', 5, 2)->default(0);
            $table->decimal('max_score_threshold', 5, 2)->default(100);

            $table->json('assessment_criteria')->nullable();
            $table->json('learning_resources')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->foreign('competency_id')
                ->references('competency_id')
                ->on('competencies')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->unique(['competency_id', 'level_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competency_levels');
    }
};
