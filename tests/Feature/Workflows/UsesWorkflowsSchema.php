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

        // The feature tests use SQLite in-memory by default. Re-create the
        // workflow tables for non-SQLite connections to keep tests isolated.
        if ($connection !== 'sqlite') {
            Schema::connection($connection)->dropIfExists('workflow_history');
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

        $this->createWorkflowHistoryTable();
    }

    protected function createWorkflowHistoryTable(): void
    {
        if (! Schema::hasTable('workflow_history')) {
            Schema::create('workflow_history', function (Blueprint $table): void {
                $table->uuid('history_id')->primary();
                $table->uuid('workflow_id');
                $table->uuid('actor_user_id');
                $table->string('action_type');
                $table->string('old_state')->nullable();
                $table->string('new_state')->nullable();
                $table->json('transition_metadata')->nullable();
                $table->timestamps();

                // Indexes match the production migration. Foreign keys are
                // intentionally omitted here because the in-memory SQLite
                // test schema is created independently from tenant migrations.
                $table->index('action_type');
                $table->index('created_at');
                $table->index(['workflow_id', 'created_at']);
                $table->index(['workflow_id', 'action_type']);
                $table->index(['actor_user_id', 'created_at']);
                $table->index(['old_state', 'new_state']);
            });
        }
    }
}
