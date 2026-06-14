<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Grading\UsesGradingSchema;

trait UsesAnalyticsSchema
{
    use UsesGradingSchema;

    protected function migrateAnalyticsTables(): void
    {
        if (! Schema::hasTable('analytics_cache')) {
            Schema::create('analytics_cache', function (Blueprint $table): void {
                $table->uuid('cache_id')->primary();
                $table->uuid('tenant_id');
                $table->string('cache_key');
                $table->json('cache_value')->nullable();
                $table->timestamp('computed_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->unique(['tenant_id', 'cache_key']);
            });
        }
    }
}
