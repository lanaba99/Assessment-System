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
            $table->uuid('tenant_id');
            $table->uuid('result_id');
            $table->uuid('session_id');
            $table->uuid('candidate_user_id');

            $table->string('certificate_number')->unique();
            $table->string('verification_token')->unique();
            $table->string('pdf_path');

            $table->timestamp('issued_at');

            $table->foreign('result_id')->references('result_id')->on('assessment_results')->onDelete('cascade');
            $table->foreign('candidate_user_id')->references('id')->on('users')->onDelete('cascade');

            $table->index('tenant_id');
            $table->index('candidate_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};