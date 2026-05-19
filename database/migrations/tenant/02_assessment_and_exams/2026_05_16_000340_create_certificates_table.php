<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->uuid('certificate_id')->primary();
            $table->uuid('candidate_user_id');
            $table->uuid('assessment_result_id');
            $table->uuid('exam_id');
            $table->uuid('tenant_id');

            $table->string('certificate_code')->unique();
            $table->text('qr_code_data')->nullable();
            $table->text('digital_signature')->nullable();

            $table->json('certificate_metadata')->nullable();

            $table->dateTime('issued_at')->nullable();
            $table->dateTime('expires_at')->nullable();

            $table->string('verification_status')->default('valid');
            $table->json('additional_credentials')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->foreign('candidate_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');

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
            $table->index('verification_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
