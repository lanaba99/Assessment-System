<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_import_logs', function (Blueprint $table) {
            $table->uuid('import_log_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('imported_by_user_id');

            $table->string('import_source');

            $table->unsignedInteger('total_questions_imported')->default(0);
            $table->unsignedInteger('successful_imports')->default(0);
            $table->unsignedInteger('failed_imports')->default(0);

            $table->json('error_details')->nullable();

            $table->timestamp('import_started_at')->nullable();
            $table->timestamp('import_completed_at')->nullable();

            $table->foreign('imported_by_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->index('tenant_id');
            $table->index('import_started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_import_logs');
    }
};
