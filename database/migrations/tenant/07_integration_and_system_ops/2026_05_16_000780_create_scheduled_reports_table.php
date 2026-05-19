<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->uuid('schedule_id')->primary();
            $table->uuid('report_id');
            $table->uuid('tenant_id');

            $table->string('frequency');
            $table->string('cron_expression')->nullable();

            $table->json('recipient_emails')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->foreign('report_id')
                ->references('report_id')
                ->on('report_definitions')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->index('tenant_id');
            $table->index('frequency');
            $table->index('is_active');
            $table->index(['report_id', 'is_active']);
            $table->index(['is_active', 'frequency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_reports');
    }
};
