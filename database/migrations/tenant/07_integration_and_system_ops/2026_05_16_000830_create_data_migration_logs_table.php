<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_migration_logs', function (Blueprint $table) {
            $table->uuid('migration_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('initiated_by_user_id')->nullable();

            $table->string('migration_type');
            $table->string('source_env');
            $table->string('target_env');

            $table->unsignedInteger('records_migrated')->default(0);

            $table->string('migration_status')->default('pending');
            $table->json('error_details')->nullable();

            $table->timestamps();

            $table->foreign('initiated_by_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->index('tenant_id');
            $table->index('migration_type');
            $table->index('migration_status');
            $table->index('created_at');
            $table->index(['migration_status', 'created_at']);
            $table->index(['source_env', 'target_env']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_migration_logs');
    }
};
