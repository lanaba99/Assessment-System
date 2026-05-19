<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_executions', function (Blueprint $table) {
            $table->uuid('execution_id')->primary();
            $table->uuid('report_id');
            $table->uuid('schedule_id')->nullable();
            $table->uuid('tenant_id');
            $table->uuid('triggered_by_user_id')->nullable();

            $table->string('file_path')->nullable();
            $table->unsignedInteger('record_count')->default(0);

            $table->string('execution_status')->default('pending');
            $table->text('error_message')->nullable();

            $table->timestamp('generated_at')->nullable();

            $table->foreign('report_id')
                ->references('report_id')
                ->on('report_definitions')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->foreign('schedule_id')
                ->references('schedule_id')
                ->on('scheduled_reports')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->foreign('triggered_by_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->index('tenant_id');
            $table->index('execution_status');
            $table->index('generated_at');
            $table->index(['report_id', 'generated_at']);
            $table->index(['schedule_id', 'generated_at']);
            $table->index(['execution_status', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_executions');
    }
};
