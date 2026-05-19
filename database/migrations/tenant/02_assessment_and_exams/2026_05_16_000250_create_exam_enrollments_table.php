<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_enrollments', function (Blueprint $table) {
            $table->uuid('enrollment_id')->primary();
            $table->uuid('exam_id');
            $table->uuid('candidate_user_id');
            $table->uuid('tenant_id');
            $table->uuid('cohort_id')->nullable();

            $table->string('enrollment_status')->default('pending');

            $table->dateTime('enrollment_date')->nullable();
            $table->dateTime('start_window_date')->nullable();
            $table->dateTime('end_window_date')->nullable();
            $table->dateTime('start_eligibility_date')->nullable();
            $table->dateTime('end_eligibility_date')->nullable();

            $table->boolean('can_retake_exam')->default(false);

            $table->unsignedInteger('max_attempts_allowed')->default(1);
            $table->unsignedInteger('attempts_used')->default(0);
            $table->unsignedInteger('attempts_remaining')->default(1);

            $table->decimal('highest_score_achieved', 6, 2)->nullable();
            $table->string('highest_score_status')->nullable();

            $table->text('enrollment_notes')->nullable();

            $table->timestamps();

            $table->foreign('exam_id')
                ->references('exam_id')
                ->on('exams')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('candidate_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->index('tenant_id');
            $table->index('cohort_id');
            $table->index('enrollment_status');
            $table->unique(['exam_id', 'candidate_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_enrollments');
    }
};
