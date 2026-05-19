<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('role_id')->primary();
            $table->uuid('tenant_id');

            $table->string('role_name')->unique();
            $table->string('description')->nullable();
            $table->string('role_category')->nullable();

            $table->boolean('is_custom_role')->default(false);
            $table->boolean('is_system_role')->default(false);

            $table->json('role_metadata')->nullable();

            $table->timestamps();

            $table->index('tenant_id');
            $table->index('role_category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
