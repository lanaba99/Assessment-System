<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->uuid('attempt_id')->primary();
            $table->uuid('tenant_id');

            $table->string('email_attempted');
            $table->string('ip_address', 45)->nullable();
            $table->boolean('is_successful')->default(false);
            $table->string('failure_reason')->nullable();
            $table->string('device_info')->nullable();

            $table->timestamp('attempted_at')->useCurrent();

            $table->index('tenant_id');
            $table->index('email_attempted');
            $table->index('ip_address');
            $table->index('attempted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
