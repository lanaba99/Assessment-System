<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_cache', function (Blueprint $table) {
            $table->uuid('cache_id')->primary();
            $table->uuid('tenant_id');

            $table->string('cache_key');
            $table->json('cache_value')->nullable();

            $table->timestamp('computed_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->unique(['tenant_id', 'cache_key']);
            $table->index('expires_at');
            $table->index(['tenant_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_cache');
    }
};
