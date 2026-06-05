<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_session_items', function (Blueprint $table): void {
            $table->uuid('session_item_id')->primary();
            $table->uuid('session_id');
            $table->uuid('section_id');
            $table->uuid('question_version_id');

            $table->unsignedInteger('sequence_number');
            $table->string('item_state')->default('pending');

            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamp('answered_at')->nullable();

            $table->boolean('is_flagged')->default(false);

            // version_lock starts as timestamp; 2026_05_22_000010 promotes it to
            // unsignedBigInteger once exam_sessions has the same promotion applied.
            $table->timestamp('version_lock')->nullable();
            $table->timestamps();

            $table->foreign('session_id')
                ->references('session_id')
                ->on('exam_sessions')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('section_id')
                ->references('section_id')
                ->on('exam_sections')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->foreign('question_version_id')
                ->references('version_id')
                ->on('question_versions')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->unique(['session_id', 'sequence_number']);
            $table->index('item_state');
            $table->index('is_flagged');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_session_items');
    }
};
