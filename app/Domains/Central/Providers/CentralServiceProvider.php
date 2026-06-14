<?php

declare(strict_types=1);

namespace App\Domains\Central\Providers;

use App\Domains\Central\Models\CentralAdminUser;
use App\Domains\Central\Policies\CentralTenantPolicy;
use App\Domains\Central\Services\CentralAuthService;
use App\Domains\Central\Services\TenantManagementService;
use App\Models\Tenant;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class CentralServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CentralAuthService::class);
        $this->app->bind(TenantManagementService::class);
    }

    public function boot(): void
    {
        Gate::policy(Tenant::class, CentralTenantPolicy::class);

        Gate::define('central-admin', function (CentralAdminUser $user): bool {
            return (bool) $user->is_super_admin
                || in_array('*', (array) $user->admin_permissions, true);
        });
    }
}
