<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_schedules', function (Blueprint $table) {
            $table->uuid('schedule_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('created_by_user_id');

            $table->string('schedule_name');
            $table->string('schedule_frequency');
            $table->string('cron_expression')->nullable();

            $table->string('backup_type');
            $table->unsignedInteger('retention_days')->default(30);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->index('tenant_id');
            $table->index('backup_type');
            $table->index('is_active');
            $table->index(['is_active', 'schedule_frequency']);
            $table->index(['backup_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_schedules');
    }
};
