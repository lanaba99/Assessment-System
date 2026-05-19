<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_import_templates', function (Blueprint $table) {
            $table->uuid('template_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('created_by_user_id');

            $table->string('template_name');
            $table->string('template_format');

            $table->json('template_mapping');
            $table->json('validation_rules')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->index('tenant_id');
            $table->index('template_format');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_import_templates');
    }
};
