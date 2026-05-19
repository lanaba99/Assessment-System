<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->uuid('log_id')->primary();
            $table->uuid('event_id');
            $table->uuid('tenant_id');

            $table->string('target_url');
            $table->unsignedInteger('attempt_number')->default(1);
            $table->unsignedSmallInteger('response_status')->nullable();

            $table->text('response_body')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('sent_at')->nullable();

            $table->foreign('event_id')
                ->references('event_id')
                ->on('webhook_events')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->index('tenant_id');
            $table->index('response_status');
            $table->index('sent_at');
            $table->index(['event_id', 'attempt_number']);
            $table->index(['event_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
