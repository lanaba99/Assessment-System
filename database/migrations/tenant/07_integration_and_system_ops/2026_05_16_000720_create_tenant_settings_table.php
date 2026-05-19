<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->uuid('setting_id')->primary();
            $table->uuid('tenant_id');

            $table->string('setting_key');
            $table->text('setting_value')->nullable();

            $table->string('field_type')->default('string');
            $table->string('setting_group')->nullable();

            $table->boolean('is_encrypted')->default(false);
            $table->boolean('is_public')->default(false);

            $table->timestamps();

            $table->unique(['tenant_id', 'setting_key']);
            $table->index('setting_group');
            $table->index('is_public');
            $table->index(['tenant_id', 'setting_group']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_settings');
    }
};
