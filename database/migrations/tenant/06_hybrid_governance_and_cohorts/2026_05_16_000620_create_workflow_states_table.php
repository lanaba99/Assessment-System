<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_states', function (Blueprint $table) {
            $table->uuid('state_id')->primary();
            $table->uuid('workflow_id');

            $table->unsignedInteger('state_sequence');

            $table->string('state_key');
            $table->string('state_name');
            $table->text('state_description')->nullable();

            $table->json('required_approvals_json')->nullable();

            $table->string('approval_pattern')->nullable();

            $table->json('state_metadata')->nullable();

            $table->foreign('workflow_id')
                ->references('workflow_id')
                ->on('approval_workflows')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->unique(['workflow_id', 'state_sequence']);
            $table->unique(['workflow_id', 'state_key']);
            $table->index('approval_pattern');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_states');
    }
};
