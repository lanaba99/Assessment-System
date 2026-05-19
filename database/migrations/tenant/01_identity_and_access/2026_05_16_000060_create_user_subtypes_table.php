<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_subtypes', function (Blueprint $table) {
            $table->uuid('user_id')->primary();

            $table->string('super_admin_scope')->nullable();
            $table->string('tenant_admin_organization')->nullable();
            $table->string('evaluator_specialization')->nullable();
            $table->string('examinee_employee_position')->nullable();

            $table->boolean('is_proctor')->default(false);

            $table->uuid('examinee_manager_id')->nullable();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('examinee_manager_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->index('examinee_manager_id');
            $table->index('is_proctor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_subtypes');
    }
};
