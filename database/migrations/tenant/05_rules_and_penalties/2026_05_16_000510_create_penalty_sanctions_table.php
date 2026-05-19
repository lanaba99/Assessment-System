<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penalty_sanctions', function (Blueprint $table) {
            $table->uuid('sanction_id')->primary();
            $table->uuid('session_id');
            $table->uuid('candidate_user_id');
            $table->uuid('penalty_rule_id');
            $table->uuid('tenant_id');

            $table->dateTime('sanction_applied_at');

            $table->string('sanction_reason');
            $table->decimal('sanction_amount', 12, 4)->nullable();
            $table->string('sanction_type');

            $table->json('sanction_metadata')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->foreign('session_id')
                ->references('session_id')
                ->on('exam_sessions')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('candidate_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('penalty_rule_id')
                ->references('penalty_rule_id')
                ->on('penalty_rules')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->index('tenant_id');
            $table->index('sanction_type');
            $table->index('sanction_applied_at');
            $table->index(['session_id', 'sanction_applied_at']);
            $table->index(['candidate_user_id', 'sanction_applied_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penalty_sanctions');
    }
};
