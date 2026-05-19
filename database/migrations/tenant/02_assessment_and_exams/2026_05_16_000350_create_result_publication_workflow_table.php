<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('result_publication_workflow', function (Blueprint $table) {
            $table->uuid('workflow_id')->primary();
            $table->uuid('assessment_result_id');
            $table->uuid('exam_id');
            $table->uuid('tenant_id');

            $table->unsignedInteger('current_approval_step')->default(0);

            $table->json('approval_chain_json')->nullable();

            $table->string('current_approval_status')->default('pending');

            $table->dateTime('submitted_for_approval_at')->nullable();
            $table->dateTime('published_at')->nullable();

            $table->json('publication_metadata')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->foreign('assessment_result_id')
                ->references('result_id')
                ->on('assessment_results')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('exam_id')
                ->references('exam_id')
                ->on('exams')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->index('tenant_id');
            $table->index('current_approval_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_publication_workflow');
    }
};
