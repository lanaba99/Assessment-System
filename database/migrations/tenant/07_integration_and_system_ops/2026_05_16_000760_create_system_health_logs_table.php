<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_health_logs', function (Blueprint $table) {
            $table->uuid('health_id')->primary();
            $table->uuid('tenant_id');

            $table->string('check_component');
            $table->string('check_status');

            $table->unsignedInteger('response_time_ms')->nullable();

            $table->text('health_message')->nullable();

            $table->timestamp('checked_at')->useCurrent();

            $table->index('tenant_id');
            $table->index('check_component');
            $table->index('check_status');
            $table->index('checked_at');
            $table->index(['check_component', 'checked_at']);
            $table->index(['check_status', 'checked_at']);
            $table->index(['check_component', 'check_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_health_logs');
    }
};
