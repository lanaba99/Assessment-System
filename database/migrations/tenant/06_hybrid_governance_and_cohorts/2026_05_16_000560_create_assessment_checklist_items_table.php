<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_checklist_items', function (Blueprint $table) {
            $table->uuid('checklist_item_id')->primary();
            $table->uuid('exam_id');
            $table->uuid('tenant_id');
            $table->uuid('created_by_user_id');

            $table->text('item_description');
            $table->json('item_assessment_criteria')->nullable();

            $table->unsignedTinyInteger('item_weight_percentage')->default(0);
            $table->unsignedInteger('display_sequence')->default(0);

            $table->string('item_category')->nullable();

            $table->json('item_metadata')->nullable();

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
            $table->index('item_category');
            $table->unique(['exam_id', 'display_sequence']);
            $table->index(['exam_id', 'item_category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_checklist_items');
    }
};
