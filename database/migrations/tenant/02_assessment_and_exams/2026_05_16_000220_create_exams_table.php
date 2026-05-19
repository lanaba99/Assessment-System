<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->uuid('exam_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('created_by_user_id');

            $table->string('exam_name');
            $table->string('exam_code')->unique();
            $table->text('exam_description')->nullable();

            $table->string('exam_type');
            $table->string('assessment_mode');

            $table->unsignedInteger('total_questions')->default(0);
            $table->unsignedInteger('total_duration_minutes')->default(0);

            $table->decimal('pass_mark_percentage', 5, 2)->default(50);
            $table->unsignedTinyInteger('difficulty_tier_level')->default(1);

            $table->boolean('is_adaptive_exam')->default(false);
            $table->boolean('is_randomized')->default(false);
            $table->boolean('allow_review_after_submit')->default(false);
            $table->boolean('allow_flagging_for_review')->default(true);
            $table->boolean('timer_visible_to_candidate')->default(true);
            $table->boolean('show_correct_answers_after')->default(false);

            $table->json('security_protocols')->nullable();
            $table->json('exam_metadata')->nullable();

            $table->boolean('is_published')->default(false);
            $table->string('exam_status')->default('draft');

            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->timestamp('archived_at')->nullable();

            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->index('tenant_id');
            $table->index('exam_type');
            $table->index('exam_status');
            $table->index('is_published');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
