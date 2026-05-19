<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->uuid('department_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('parent_department_id')->nullable();

            $table->string('department_name');
            $table->string('department_code')->unique();

            $table->uuid('department_manager_id')->nullable();

            $table->unsignedInteger('hierarchy_level')->default(0);

            $table->json('department_attributes')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('tenant_id');
            $table->index('parent_department_id');

            $table->foreign('parent_department_id')
                ->references('department_id')
                ->on('departments')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->foreign('department_manager_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('set null');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('department_id')
                ->references('department_id')
                ->on('departments')
                ->onUpdate('cascade')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
        });

        Schema::dropIfExists('departments');
    }
};
