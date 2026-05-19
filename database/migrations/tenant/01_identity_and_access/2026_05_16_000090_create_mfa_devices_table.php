<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfa_devices', function (Blueprint $table) {
            $table->uuid('mfa_device_id')->primary();
            $table->uuid('user_id');
            $table->uuid('tenant_id');

            $table->string('device_type');
            $table->string('device_identifier');
            $table->string('device_name')->nullable();
            $table->string('secret_key_hash');

            $table->boolean('is_backup_code')->default(false);
            $table->boolean('is_verified')->default(false);

            $table->unsignedInteger('backup_codes_count')->default(0);

            $table->timestamp('verified_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('last_used_at')->nullable();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->index('tenant_id');
            $table->index('user_id');
            $table->index('device_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfa_devices');
    }
};
