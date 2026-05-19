<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('central_api_keys', function (Blueprint $table) {
            $table->uuid('key_id')->primary();
            $table->uuid('tenant_id');

            $table->string('key_prefix');
            $table->string('key_hash');
            $table->string('secret_hash');
            $table->string('key_description')->nullable();

            $table->json('granted_permissions')->nullable();

            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();

            $table->string('status')->default('active');
            $table->string('ip_restriction')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->index('tenant_id');
            $table->index('key_prefix');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('central_api_keys');
    }
};
