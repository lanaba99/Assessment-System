<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penalty_rules', function (Blueprint $table) {
            $table->uuid('penalty_rule_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('created_by_user_id');

            $table->string('penalty_name');
            $table->string('penalty_type');

            $table->string('trigger_condition');
            $table->json('trigger_parameters')->nullable();

            $table->decimal('penalty_points', 10, 4)->nullable();
            $table->decimal('penalty_percentage', 5, 2)->nullable();

            $table->boolean('is_cumulative')->default(false);

            $table->json('penalty_metadata')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->index('tenant_id');
            $table->index('penalty_type');
            $table->index('trigger_condition');
            $table->index('is_active');
            $table->index('is_cumulative');
            $table->index(['penalty_type', 'is_active']);
            $table->index(['trigger_condition', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penalty_rules');
    }
};
