<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_audit_logs', function (Blueprint $table) {
            $table->uuid('log_id')->primary();
            $table->uuid('key_id')->nullable();
            $table->uuid('tenant_id');

            $table->string('request_path');
            $table->string('request_method', 10);
            $table->unsignedSmallInteger('response_status');

            $table->string('client_ip', 45)->nullable();
            $table->unsignedInteger('execution_time_ms')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->foreign('key_id')
                ->references('key_id')
                ->on('tenant_api_keys')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->index('tenant_id');
            $table->index('request_method');
            $table->index('response_status');
            $table->index('created_at');
            $table->index(['key_id', 'created_at']);
            $table->index(['request_path', 'created_at']);
            $table->index(['response_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_audit_logs');
    }
};
