<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bridge_entry_hybrid', function (Blueprint $table) {
            $table->uuid('bridge_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('exam_id');
            $table->uuid('candidate_user_id');
            $table->uuid('correlated_session_id')->nullable();

            $table->string('entry_source_system');

            $table->json('paper_assessment_data')->nullable();
            $table->json('digital_assessment_data')->nullable();
            $table->json('merged_assessment_data')->nullable();

            $table->string('merge_status')->default('pending');

            $table->json('merge_metadata')->nullable();

            $table->timestamp('paper_data_received_at')->nullable();
            $table->timestamp('digital_data_received_at')->nullable();
            $table->timestamp('merged_at')->nullable();

            $table->timestamps();

            $table->foreign('exam_id')
                ->references('exam_id')
                ->on('exams')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->foreign('candidate_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->foreign('correlated_session_id')
                ->references('session_id')
                ->on('exam_sessions')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->index('tenant_id');
            $table->index('entry_source_system');
            $table->index('merge_status');
            $table->index(['exam_id', 'merge_status']);
            $table->index(['candidate_user_id', 'merge_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bridge_entry_hybrid');
    }
};
