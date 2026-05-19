<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cohort_members', function (Blueprint $table) {
            $table->uuid('member_id')->primary();
            $table->uuid('cohort_id');
            $table->uuid('user_id');
            $table->uuid('tenant_id');

            $table->string('membership_role')->default('member');

            $table->timestamp('added_at')->useCurrent();
            $table->timestamp('removed_at')->nullable();

            $table->boolean('is_active_member')->default(true);

            $table->foreign('cohort_id')
                ->references('cohort_id')
                ->on('cohorts')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->unique(['cohort_id', 'user_id']);
            $table->index('tenant_id');
            $table->index('membership_role');
            $table->index('is_active_member');
            $table->index(['cohort_id', 'is_active_member']);
            $table->index(['user_id', 'is_active_member']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cohort_members');
    }
};
