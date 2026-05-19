<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->uuid('sync_id')->primary();
            $table->uuid('tenant_id');

            $table->string('entity_type');
            $table->string('external_system_name');
            $table->string('operation_type');

            $table->unsignedInteger('records_processed')->default(0);
            $table->unsignedInteger('records_failed')->default(0);

            $table->string('sync_status')->default('pending');
            $table->json('error_details')->nullable();

            $table->timestamps();

            $table->index('tenant_id');
            $table->index('entity_type');
            $table->index('external_system_name');
            $table->index('operation_type');
            $table->index('sync_status');
            $table->index('created_at');
            $table->index(['external_system_name', 'sync_status']);
            $table->index(['entity_type', 'sync_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
