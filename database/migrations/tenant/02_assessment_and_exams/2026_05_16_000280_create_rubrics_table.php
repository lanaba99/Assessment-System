<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rubrics', function (Blueprint $table) {
            $table->uuid('rubric_id')->primary();
            $table->uuid('exam_id');
            $table->uuid('created_by_user_id');
            $table->uuid('tenant_id');

            $table->string('rubric_name');
            $table->string('rubric_type');
            $table->text('rubric_description')->nullable();

            $table->json('rubric_structure')->nullable();

            $table->boolean('is_mandatory_rubric')->default(false);

            $table->timestamps();

            $table->foreign('exam_id')
                ->references('exam_id')
                ->on('exams')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->index('tenant_id');
            $table->index('rubric_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rubrics');
    }
};
