<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->uuid('event_id')->primary();
            $table->uuid('tenant_id');

            $table->string('event_type');
            $table->json('payload')->nullable();

            $table->boolean('is_processed')->default(false);

            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('tenant_id');
            $table->index('event_type');
            $table->index('is_processed');
            $table->index('created_at');
            $table->index(['is_processed', 'created_at']);
            $table->index(['event_type', 'is_processed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
