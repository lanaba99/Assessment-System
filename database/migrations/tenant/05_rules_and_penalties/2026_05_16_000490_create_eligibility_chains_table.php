<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eligibility_chains', function (Blueprint $table) {
            $table->uuid('chain_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('exam_id');
            $table->uuid('created_by_user_id');

            $table->unsignedInteger('chain_step_number');

            $table->uuid('prerequisite_exam_id')->nullable();

            $table->string('condition_type');
            $table->json('condition_data')->nullable();

            $table->string('logical_operator')->nullable();

            $table->decimal('min_score_required', 6, 2)->nullable();

            $table->boolean('is_satisfied_override_available')->default(false);
            $table->uuid('override_authorized_by_user_id')->nullable();

            $table->json('chain_metadata')->nullable();

            $table->timestamps();

            $table->foreign('exam_id')
                ->references('exam_id')
                ->on('exams')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('prerequisite_exam_id')
                ->references('exam_id')
                ->on('exams')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->foreign('override_authorized_by_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->index('tenant_id');
            $table->index('condition_type');
            $table->index('logical_operator');
            $table->index('is_satisfied_override_available');
            $table->unique(['exam_id', 'chain_step_number']);
            $table->index(['exam_id', 'prerequisite_exam_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eligibility_chains');
    }
};
