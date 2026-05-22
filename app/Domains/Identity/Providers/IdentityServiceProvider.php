<?php

declare(strict_types=1);

namespace App\Domains\Identity\Providers;

use App\Domains\Identity\Contracts\AuthenticationService;
use App\Domains\Identity\Contracts\AuthorizationService;
use App\Domains\Identity\Contracts\MfaService;
use App\Domains\Identity\Contracts\RoleManagementService;
use App\Domains\Identity\Contracts\UserManagementService;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\SecurityPolicy;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Policies\RolePolicy;
use App\Domains\Identity\Policies\SecurityPolicyPolicy;
use App\Domains\Identity\Policies\UserPolicy;
use App\Domains\Identity\Services\AuthenticationServiceImpl;
use App\Domains\Identity\Services\AuthorizationServiceImpl;
use App\Domains\Identity\Services\MfaServiceImpl;
use App\Domains\Identity\Services\RoleManagementServiceImpl;
use App\Domains\Identity\Services\UserManagementServiceImpl;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MfaService::class, MfaServiceImpl::class);
        $this->app->bind(AuthorizationService::class, AuthorizationServiceImpl::class);
        $this->app->bind(AuthenticationService::class, AuthenticationServiceImpl::class);
        $this->app->bind(UserManagementService::class, UserManagementServiceImpl::class);
        $this->app->bind(RoleManagementService::class, RoleManagementServiceImpl::class);
    }

    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(SecurityPolicy::class, SecurityPolicyPolicy::class);
    }
}
