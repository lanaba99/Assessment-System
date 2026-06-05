<?php

declare(strict_types=1);

namespace App\Domains\Competency\Providers;

use App\Domains\Competency\Contracts\CompetencyTreeService;
use App\Domains\Competency\Models\Competency;
use App\Domains\Competency\Policies\CompetencyPolicy;
use App\Domains\Competency\Services\CompetencyTreeServiceImpl;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Auto-registered by App\Providers\DomainServiceProvider, which scans each
 * domain's Providers directory. No manual registration in
 * bootstrap/providers.php is required.
 */
class CompetencyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CompetencyTreeService::class, CompetencyTreeServiceImpl::class);
    }

    public function boot(): void
    {
        Gate::policy(Competency::class, CompetencyPolicy::class);
    }
}
