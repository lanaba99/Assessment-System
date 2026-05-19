<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_approvals', function (Blueprint $table) {
            $table->uuid('approval_id')->primary();
            $table->uuid('workflow_id');
            $table->uuid('approver_user_id');

            $table->unsignedInteger('approval_stage_number');

            $table->string('approval_status')->default('pending');

            $table->json('approval_evidence_json')->nullable();
            $table->json('approval_comments')->nullable();

            $table->dateTime('approved_at')->nullable();
            $table->dateTime('approval_deadline_at')->nullable();

            $table->boolean('can_reject')->default(true);
            $table->boolean('can_request_changes')->default(true);

            $table->timestamps();

            $table->foreign('workflow_id')
                ->references('workflow_id')
                ->on('approval_workflows')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('approver_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->index('approval_status');
            $table->index('approval_deadline_at');
            $table->index(['workflow_id', 'approval_stage_number']);
            $table->index(['approver_user_id', 'approval_status']);
            $table->index(['workflow_id', 'approval_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_approvals');
    }
};
