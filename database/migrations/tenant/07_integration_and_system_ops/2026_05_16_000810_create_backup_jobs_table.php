<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_jobs', function (Blueprint $table) {
            $table->uuid('job_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('initiated_by_user_id')->nullable();

            $table->string('backup_type');
            $table->unsignedBigInteger('backup_size_bytes')->default(0);

            $table->string('backup_location')->nullable();
            $table->string('backup_status')->default('pending');

            $table->json('backup_metadata')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('backup_completed_at')->nullable();

            $table->foreign('initiated_by_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->index('tenant_id');
            $table->index('backup_type');
            $table->index('backup_status');
            $table->index('created_at');
            $table->index(['backup_status', 'created_at']);
            $table->index(['backup_type', 'backup_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_jobs');
    }
};
