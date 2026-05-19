<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_psychometrics', function (Blueprint $table) {
            $table->uuid('psychometric_id')->primary();
            $table->uuid('question_version_id');
            $table->uuid('tenant_id');

            $table->decimal('difficulty_index', 6, 4)->nullable();
            $table->decimal('discrimination_index', 6, 4)->nullable();
            $table->decimal('point_biserial', 6, 4)->nullable();

            $table->unsignedInteger('sample_size')->default(0);
            $table->unsignedInteger('correct_count')->default(0);

            $table->boolean('is_calibrated')->default(false);
            $table->string('calibration_status')->default('pending');

            $table->json('calibration_metadata')->nullable();

            $table->timestamp('last_calibrated_at')->nullable();
            $table->timestamps();

            $table->foreign('question_version_id')
                ->references('version_id')
                ->on('question_versions')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->unique('question_version_id');
            $table->index('tenant_id');
            $table->index('is_calibrated');
            $table->index('discrimination_index');
            $table->index('difficulty_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_psychometrics');
    }
};
