<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cohorts', function (Blueprint $table) {
            $table->uuid('cohort_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('parent_cohort_id')->nullable();
            $table->uuid('created_by_user_id');

            $table->string('cohort_name');
            $table->string('cohort_code')->unique();
            $table->string('cohort_type');
            $table->text('cohort_description')->nullable();

            $table->unsignedInteger('hierarchy_level')->default(0);

            $table->json('cohort_attributes')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->foreign('parent_cohort_id')
                ->references('cohort_id')
                ->on('cohorts')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->index('tenant_id');
            $table->index('cohort_type');
            $table->index('hierarchy_level');
            $table->index('is_active');
            $table->index(['cohort_type', 'is_active']);
            $table->index(['parent_cohort_id', 'hierarchy_level']);
        });

        Schema::table('exam_enrollments', function (Blueprint $table) {
            $table->foreign('cohort_id')
                ->references('cohort_id')
                ->on('cohorts')
                ->onUpdate('cascade')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('exam_enrollments', function (Blueprint $table) {
            $table->dropForeign(['cohort_id']);
        });

        Schema::dropIfExists('cohorts');
    }
};
