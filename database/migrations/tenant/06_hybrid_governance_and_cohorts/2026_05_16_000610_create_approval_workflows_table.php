<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->uuid('workflow_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('initiated_by_user_id');

            $table->uuid('resource_id');
            $table->string('resource_type');

            $table->string('workflow_type');

            $table->json('workflow_stages_json')->nullable();

            $table->string('current_stage_key')->nullable();
            $table->string('current_workflow_status')->default('pending');

            $table->dateTime('workflow_initiated_at')->nullable();
            $table->dateTime('workflow_completed_at')->nullable();

            $table->json('workflow_metadata')->nullable();

            $table->timestamps();

            $table->foreign('initiated_by_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->index('tenant_id');
            $table->index('workflow_type');
            $table->index('current_workflow_status');
            $table->index('current_stage_key');
            $table->index(['resource_type', 'resource_id']);
            $table->index(['workflow_type', 'current_workflow_status']);
            $table->index(['resource_type', 'current_workflow_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_workflows');
    }
};
