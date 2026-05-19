<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('permission_id')->primary();
            $table->uuid('tenant_id');

            $table->string('permission_name')->unique();
            $table->string('resource_type');
            $table->string('action_type');
            $table->string('description')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index('tenant_id');
            $table->index('resource_type');
            $table->index('action_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
