<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_definitions', function (Blueprint $table) {
            $table->uuid('report_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('created_by_user_id');

            $table->string('report_name');
            $table->string('report_type');

            $table->json('query_configuration')->nullable();
            $table->json('visual_layout')->nullable();

            $table->timestamps();

            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->index('tenant_id');
            $table->index('report_type');
            $table->index(['tenant_id', 'report_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_definitions');
    }
};
