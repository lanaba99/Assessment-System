<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('central_audit_trails', function (Blueprint $table) {
            $table->uuid('audit_id')->primary();

            $table->uuid('tenant_id')->nullable();
            $table->uuid('super_admin_id')->nullable();

            $table->string('action_type');
            $table->string('resource_type');
            $table->uuid('resource_id')->nullable();

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('session_id')->nullable();
            $table->string('audit_hash');

            $table->timestamp('created_at')->nullable();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->foreign('super_admin_id')
                ->references('admin_user_id')
                ->on('central_admin_users')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->index('tenant_id');
            $table->index('super_admin_id');
            $table->index('action_type');
            $table->index('resource_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('central_audit_trails');
    }
};
