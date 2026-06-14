<?php

declare(strict_types=1);

namespace App\Domains\Penalties\Providers;

use App\Domains\Penalties\Listeners\ApplyPenaltyOnProctorEventListener;
use App\Domains\Penalties\Models\PenaltyRule;
use App\Domains\Penalties\Models\PenaltySanction;
use App\Domains\Penalties\Policies\PenaltyPolicy;
use App\Domains\Penalties\Services\PenaltyEvaluationService;
use App\Domains\Penalties\Services\PenaltyRuleManagementService;
use App\Domains\Penalties\Services\PenaltySanctionService;
use App\Domains\Penalties\Triggers\ProctorEventTypeTrigger;
use App\Domains\Penalties\Triggers\SeverityThresholdTrigger;
use App\Domains\Proctoring\Events\ProctorEventLogged;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class PenaltiesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PenaltyEvaluationService::class, function (Container $app): PenaltyEvaluationService {
            return new PenaltyEvaluationService(
                rules: $app->make(\App\Domains\Penalties\Repositories\PenaltyRuleRepository::class),
                sanctions: $app->make(\App\Domains\Penalties\Repositories\PenaltySanctionRepository::class),
                triggers: [
                    $app->make(ProctorEventTypeTrigger::class),
                    $app->make(SeverityThresholdTrigger::class),
                ],
            );
        });

        $this->app->bind(PenaltyRuleManagementService::class);
        $this->app->bind(PenaltySanctionService::class);
    }

    public function boot(): void
    {
        Gate::policy(PenaltyRule::class, PenaltyPolicy::class);
        Gate::policy(PenaltySanction::class, PenaltyPolicy::class);

        Event::listen(ProctorEventLogged::class, [ApplyPenaltyOnProctorEventListener::class, 'handle']);
    }
}
