<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ksa_matrix_templates', function (Blueprint $table) {
            $table->uuid('template_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('created_by_user_id');

            $table->string('template_name');
            $table->text('template_description')->nullable();

            $table->json('competency_structure')->nullable();
            $table->json('default_weights')->nullable();

            $table->boolean('is_global_template')->default(false);

            $table->timestamp('created_at')->nullable();

            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->index('tenant_id');
            $table->index('is_global_template');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ksa_matrix_templates');
    }
};
