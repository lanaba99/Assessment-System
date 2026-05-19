<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rule_templates', function (Blueprint $table) {
            $table->uuid('template_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('created_by_user_id');

            $table->string('template_name');
            $table->text('template_description')->nullable();

            $table->json('rule_template_definition');
            $table->json('action_template_definition')->nullable();

            $table->boolean('is_global_template')->default(false);

            $table->timestamp('created_at')->nullable();

            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->index('tenant_id');
            $table->index('is_global_template');
            $table->index('template_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_templates');
    }
};
