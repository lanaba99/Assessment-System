<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_responses', function (Blueprint $table) {
            $table->uuid('response_id')->primary();
            $table->uuid('manual_assessment_id');
            $table->uuid('checklist_item_id');

            $table->boolean('item_checked')->default(false);

            $table->json('response_evidence_json')->nullable();
            $table->json('response_notes')->nullable();
            $table->text('evaluator_comment')->nullable();

            $table->timestamp('evaluated_at')->nullable();

            $table->foreign('manual_assessment_id')
                ->references('assessment_id')
                ->on('manual_assessments')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('checklist_item_id')
                ->references('checklist_item_id')
                ->on('assessment_checklist_items')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->unique(['manual_assessment_id', 'checklist_item_id'], 'checklist_resp_assessment_item_unq');
            $table->index('item_checked');
            $table->index('evaluated_at');
            $table->index(['manual_assessment_id', 'item_checked']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_responses');
    }
};
