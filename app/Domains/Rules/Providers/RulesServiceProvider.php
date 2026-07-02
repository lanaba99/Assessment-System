<?php

declare(strict_types=1);

namespace App\Domains\Rules\Providers;

use App\Domains\Rules\Conditions\PrerequisiteExamConditionEvaluator;
use App\Domains\Rules\Models\EligibilityChain;
use App\Domains\Rules\Policies\EligibilityPolicy;
use App\Domains\Rules\Services\EligibilityChainManagementService;
use App\Domains\Rules\Services\EligibilityEvaluatorService;
use App\Domains\Rules\Services\RuleEngineService;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class RulesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RuleEngineService::class, function (Container $app): RuleEngineService {
            return new RuleEngineService(
                evaluators: [
                    $app->make(PrerequisiteExamConditionEvaluator::class),
                ],
            );
        });

        $this->app->bind(EligibilityEvaluatorService::class);
        $this->app->bind(EligibilityChainManagementService::class);
    }

    public function boot(): void
    {
        Gate::policy(EligibilityChain::class, EligibilityPolicy::class);
    }
}