<?php

declare(strict_types=1);

namespace App\Domains\Cohorts\Providers;

use App\Domains\Cohorts\Contracts\CohortManagementService;
use App\Domains\Cohorts\Contracts\CohortMemberService;
use App\Domains\Cohorts\Models\Cohort;
use App\Domains\Cohorts\Policies\CohortPolicy;
use App\Domains\Cohorts\Services\CohortManagementServiceImpl;
use App\Domains\Cohorts\Services\CohortMemberServiceImpl;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Auto-registered by App\Providers\DomainServiceProvider, which scans each
 * domain's Providers directory. No manual registration in
 * bootstrap/providers.php is required.
 */
class CohortServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CohortManagementService::class, CohortManagementServiceImpl::class);
        $this->app->bind(CohortMemberService::class, CohortMemberServiceImpl::class);
    }

    public function boot(): void
    {
        Gate::policy(Cohort::class, CohortPolicy::class);
    }
}
