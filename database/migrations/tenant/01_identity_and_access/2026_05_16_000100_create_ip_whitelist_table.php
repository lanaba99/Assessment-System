<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_whitelist', function (Blueprint $table) {
            $table->uuid('whitelist_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('created_by_user_id')->nullable();

            $table->string('ip_address', 45)->unique();
            $table->string('ip_description')->nullable();
            $table->string('ip_range_end', 45)->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamp('created_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->index('tenant_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_whitelist');
    }
};
