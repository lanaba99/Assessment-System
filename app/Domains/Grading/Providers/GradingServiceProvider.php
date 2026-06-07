<?php

declare(strict_types=1);

namespace App\Domains\Grading\Providers;

use App\Domains\ExamSession\Events\ExamSessionCompleted;
use App\Domains\ExamSession\Events\ResponseSubmitted;
use App\Domains\Grading\Events\ResultGenerated;
use App\Domains\Grading\Listeners\ExamSessionCompletedListener;
use App\Domains\Grading\Listeners\LogResultGeneratedListener;
use App\Domains\Grading\Listeners\ResponseSubmittedListener;
use App\Domains\Grading\Strategies\GradingStrategyResolver;
use App\Domains\Grading\Strategies\ManualReviewStrategy;
use App\Domains\Grading\Strategies\MultipleChoiceStrategy;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class GradingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GradingStrategyResolver::class, function (Container $app): GradingStrategyResolver {
            return new GradingStrategyResolver(
                strategies: [
                    $app->make(MultipleChoiceStrategy::class),
                    $app->make(ManualReviewStrategy::class),
                ],
                fallback: $app->make(ManualReviewStrategy::class),
            );
        });
    }

    public function boot(): void
    {
        Event::listen(ResponseSubmitted::class, [ResponseSubmittedListener::class, 'handle']);
        Event::listen(ExamSessionCompleted::class, [ExamSessionCompletedListener::class, 'handle']);

        // Temporary placeholder — see LogResultGeneratedListener for the TODO.
        // Replace with an Analytics module listener when that domain is built.
        Event::listen(ResultGenerated::class, [LogResultGeneratedListener::class, 'handle']);
    }
}
