<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_api_keys', function (Blueprint $table) {
            $table->uuid('key_id')->primary();
            $table->uuid('tenant_id');

            $table->string('key_prefix');
            $table->string('key_hash');
            $table->string('key_description')->nullable();

            $table->json('permissions')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();

            $table->index('tenant_id');
            $table->index('key_prefix');
            $table->index('is_active');
            $table->index('expires_at');
            $table->index(['tenant_id', 'is_active']);
            $table->index(['is_active', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_api_keys');
    }
};
