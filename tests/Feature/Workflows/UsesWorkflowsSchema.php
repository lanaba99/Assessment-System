<?php

declare(strict_types=1);

namespace Tests\Feature\Workflows;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait UsesWorkflowsSchema
{
    protected function migrateWorkflowTables(): void
    {
        $connection = (string) config('database.default');

        if ($connection !== 'sqlite') {
            Schema::connection($connection)->dropIfExists('approval_workflows');
        }

        if (! Schema::hasTable('approval_workflows')) {
            Schema::create('approval_workflows', function (Blueprint $table): void {
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
            });
        }
    }
}
