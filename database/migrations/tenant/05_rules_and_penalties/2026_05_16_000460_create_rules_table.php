<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rules', function (Blueprint $table) {
            $table->uuid('rule_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('created_by_user_id');

            $table->string('rule_name')->unique();
            $table->string('rule_type');
            $table->string('rule_scope');
            $table->string('rule_category')->nullable();

            $table->json('condition_tree_json')->nullable();
            $table->json('action_payload_json')->nullable();

            $table->boolean('is_active')->default(true);
            $table->integer('execution_order')->default(0);

            $table->json('rule_metadata')->nullable();

            $table->timestamps();
            $table->timestamp('last_executed_at')->nullable();

            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->index('tenant_id');
            $table->index('rule_type');
            $table->index('rule_scope');
            $table->index('rule_category');
            $table->index('is_active');
            $table->index('execution_order');
            $table->index(['rule_scope', 'is_active']);
            $table->index(['rule_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rules');
    }
};
