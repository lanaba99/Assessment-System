<?php

declare(strict_types=1);

namespace App\Domains\Analytics\Providers;

use App\Domains\Analytics\Listeners\IngestResultGeneratedListener;
use App\Domains\Analytics\Models\AnalyticsCache;
use App\Domains\Analytics\Policies\AnalyticsPolicy;
use App\Domains\Analytics\Services\AnalyticsIngestionService;
use App\Domains\Grading\Events\ResultGenerated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AnalyticsIngestionService::class);
    }

    public function boot(): void
    {
        Gate::policy(AnalyticsCache::class, AnalyticsPolicy::class);

        Event::listen(ResultGenerated::class, [IngestResultGeneratedListener::class, 'handle']);
    }
}
