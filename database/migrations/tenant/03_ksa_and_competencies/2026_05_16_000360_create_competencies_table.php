<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competencies', function (Blueprint $table) {
            $table->uuid('competency_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('created_by_user_id');

            $table->string('competency_name')->unique();
            $table->string('competency_code')->nullable();
            $table->string('competency_type');
            $table->string('competency_category')->nullable();
            $table->text('description')->nullable();

            $table->json('competency_attributes')->nullable();

            $table->boolean('is_mandatory')->default(false);
            $table->boolean('is_active')->default(true);

            $table->unsignedTinyInteger('proficiency_level_count')->default(5);

            $table->timestamps();

            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->index('tenant_id');
            $table->index('competency_type');
            $table->index('is_active');
        });

        // Guarded so this migration runs against a partial schema (e.g. the
        // focused competency test harness) that has not built these tables.
        if (Schema::hasTable('exam_blueprints')) {
            Schema::table('exam_blueprints', function (Blueprint $table) {
                $table->foreign('competency_id')
                    ->references('competency_id')
                    ->on('competencies')
                    ->onUpdate('cascade')
                    ->onDelete('restrict');
            });
        }

        if (Schema::hasTable('competency_scores')) {
            Schema::table('competency_scores', function (Blueprint $table) {
                $table->foreign('competency_id')
                    ->references('competency_id')
                    ->on('competencies')
                    ->onUpdate('cascade')
                    ->onDelete('restrict');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('competency_scores')) {
            Schema::table('competency_scores', function (Blueprint $table) {
                $table->dropForeign(['competency_id']);
            });
        }

        if (Schema::hasTable('exam_blueprints')) {
            Schema::table('exam_blueprints', function (Blueprint $table) {
                $table->dropForeign(['competency_id']);
            });
        }

        Schema::dropIfExists('competencies');
    }
};
