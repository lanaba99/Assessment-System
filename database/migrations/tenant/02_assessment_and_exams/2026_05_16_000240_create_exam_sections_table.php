<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_sections', function (Blueprint $table) {
            $table->uuid('section_id')->primary();
            $table->uuid('exam_id');

            $table->string('section_name');
            $table->string('section_code')->nullable();

            $table->unsignedInteger('section_sequence')->default(0);
            $table->unsignedInteger('questions_in_section')->default(0);
            $table->unsignedInteger('time_limit_minutes')->nullable();

            $table->json('branching_logic')->nullable();
            $table->json('section_metadata')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->foreign('exam_id')
                ->references('exam_id')
                ->on('exams')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->unique(['exam_id', 'section_sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_sections');
    }
};
