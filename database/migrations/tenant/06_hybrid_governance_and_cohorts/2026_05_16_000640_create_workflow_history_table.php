<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_history', function (Blueprint $table) {
            $table->uuid('history_id')->primary();
            $table->uuid('workflow_id');
            $table->uuid('actor_user_id');

            $table->string('action_type');

            $table->string('old_state')->nullable();
            $table->string('new_state')->nullable();

            $table->json('transition_metadata')->nullable();

            $table->timestamps();

            $table->foreign('workflow_id')
                ->references('workflow_id')
                ->on('approval_workflows')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('actor_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->index('action_type');
            $table->index('created_at');
            $table->index(['workflow_id', 'created_at']);
            $table->index(['workflow_id', 'action_type']);
            $table->index(['actor_user_id', 'created_at']);
            $table->index(['old_state', 'new_state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_history');
    }
};
