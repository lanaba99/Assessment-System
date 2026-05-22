<?php

declare(strict_types=1);

namespace App\Domains\Rules\Providers;

use App\Domains\Rules\Conditions\PrerequisiteExamConditionEvaluator;
use App\Domains\Rules\Services\RuleEngineService;
use Illuminate\Contracts\Container\Container;
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
    }

    public function boot(): void
    {
    }
}
