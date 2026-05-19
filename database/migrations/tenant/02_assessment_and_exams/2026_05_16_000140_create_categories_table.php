<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('category_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('parent_category_id')->nullable();

            $table->string('category_name');
            $table->string('category_code')->unique();
            $table->text('category_description')->nullable();

            $table->unsignedInteger('display_order')->default(0);
            $table->unsignedInteger('hierarchy_level')->default(0);

            $table->boolean('is_locked')->default(false);
            $table->boolean('is_active')->default(true);

            $table->json('category_metadata')->nullable();

            $table->timestamps();

            $table->foreign('parent_category_id')
                ->references('category_id')
                ->on('categories')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->index('tenant_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
