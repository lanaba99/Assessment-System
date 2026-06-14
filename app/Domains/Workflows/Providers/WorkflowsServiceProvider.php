<?php

declare(strict_types=1);

namespace App\Domains\Workflows\Providers;

use App\Domains\Workflows\Models\ApprovalWorkflow;
use App\Domains\Workflows\Policies\ApprovalWorkflowPolicy;
use App\Domains\Workflows\Services\ApprovalWorkflowService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class WorkflowsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ApprovalWorkflowService::class);
    }

    public function boot(): void
    {
        Gate::policy(ApprovalWorkflow::class, ApprovalWorkflowPolicy::class);
    }
}
