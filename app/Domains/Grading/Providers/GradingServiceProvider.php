<?php

declare(strict_types=1);

namespace App\Domains\Grading\Providers;

use App\Domains\ExamSession\Events\ExamSessionCompleted;
use App\Domains\ExamSession\Events\ResponseSubmitted;
use App\Domains\ExamEngine\Repositories\ExamBlueprintRepository;
use App\Domains\Grading\Contracts\AssessmentFinalizationService;
use App\Domains\Grading\Contracts\AssessmentResultService;
use App\Domains\Grading\Contracts\GradingService;
use App\Domains\Grading\Contracts\ManualEvaluationService;
use App\Domains\Grading\Events\ResultGenerated;
use App\Domains\Grading\Repositories\CompetencyScoreRepository;
use App\Domains\Grading\Services\CompetencyScoringService;
use App\Domains\Grading\Services\WeightedScoringService;
use App\Domains\Grading\Listeners\ExamSessionCompletedListener;
use App\Domains\Grading\Listeners\LogResultGeneratedListener;
use App\Domains\Grading\Listeners\ResponseSubmittedListener;
use App\Domains\Grading\Services\AssessmentFinalizationServiceImpl;
use App\Domains\Grading\Services\AssessmentResultServiceImpl;
use App\Domains\Grading\Services\GradingServiceImpl;
use App\Domains\Grading\Services\ManualEvaluationServiceImpl;
use App\Domains\Grading\Services\PenaltyApplicationService;
use App\Domains\Grading\Models\AnswerEvaluation;
use App\Domains\Grading\Policies\GradingPolicy;
use App\Domains\Grading\Strategies\GradingStrategyResolver;
use App\Domains\Grading\Strategies\ManualReviewStrategy;
use App\Domains\Grading\Strategies\MultipleChoiceStrategy;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Auto-registered by App\Providers\DomainServiceProvider.
 */
class GradingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Strategy resolver — singleton because strategy instances are stateless.
        $this->app->singleton(GradingStrategyResolver::class, function (Container $app): GradingStrategyResolver {
            return new GradingStrategyResolver(
                strategies: [
                    $app->make(MultipleChoiceStrategy::class),
                    $app->make(ManualReviewStrategy::class),
                ],
                fallback: $app->make(ManualReviewStrategy::class),
            );
        });

        // Service contracts → implementations (Phase A-1).
        $this->app->bind(GradingService::class, GradingServiceImpl::class);
        $this->app->bind(AssessmentFinalizationService::class, AssessmentFinalizationServiceImpl::class);
        $this->app->bind(AssessmentResultService::class, AssessmentResultServiceImpl::class);

        // Phase B supporting services — concrete classes, auto-resolvable by Laravel's
        // container, but registered here for discoverability and explicit documentation.
        $this->app->bind(WeightedScoringService::class);         // pure domain logic, stateless
        $this->app->bind(CompetencyScoringService::class);       // depends on CompetencyScoreRepository
        $this->app->bind(ExamBlueprintRepository::class);        // cross-domain read from ExamEngine
        $this->app->bind(CompetencyScoreRepository::class);

        // Phase C — manual grading workflow
        $this->app->bind(ManualEvaluationService::class, ManualEvaluationServiceImpl::class);

        // Phase D — penalty integration (read-only projection from Penalties domain)
        $this->app->bind(PenaltyApplicationService::class);
    }

    public function boot(): void
    {
        Gate::policy(AnswerEvaluation::class, GradingPolicy::class);

        Event::listen(ResponseSubmitted::class, [ResponseSubmittedListener::class, 'handle']);
        Event::listen(ExamSessionCompleted::class, [ExamSessionCompletedListener::class, 'handle']);

        // Temporary placeholder — see LogResultGeneratedListener for the TODO.
        // Replace with an Analytics module listener when that domain is built.
        Event::listen(ResultGenerated::class, [LogResultGeneratedListener::class, 'handle']);
    }
}
