<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_dashboards', function (Blueprint $table) {
            $table->uuid('dashboard_id')->primary();
            $table->uuid('cohort_id');
            $table->uuid('created_by_user_id');
            $table->uuid('tenant_id');

            $table->string('dashboard_name');

            $table->json('dashboard_config')->nullable();
            $table->json('dashboard_widgets')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->foreign('cohort_id')
                ->references('cohort_id')
                ->on('cohorts')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->index('tenant_id');
            $table->index('is_active');
            $table->index(['cohort_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_dashboards');
    }
};
