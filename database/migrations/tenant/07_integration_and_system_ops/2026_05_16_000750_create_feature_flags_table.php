<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->uuid('flag_id')->primary();
            $table->uuid('tenant_id');

            $table->string('flag_key');

            $table->boolean('is_enabled')->default(false);

            $table->decimal('roll_out_percentage', 5, 2)->default(0);

            $table->json('constraint_rules')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'flag_key']);
            $table->index('is_enabled');
            $table->index(['tenant_id', 'is_enabled']);
            $table->index(['flag_key', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flags');
    }
};
