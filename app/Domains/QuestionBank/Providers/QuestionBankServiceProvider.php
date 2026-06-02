<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Providers;

use App\Domains\ExamSession\Events\ExamSessionCompleted;
use App\Domains\QuestionBank\Contracts\CategoryTreeService;
use App\Domains\QuestionBank\Contracts\QuestionBankService;
use App\Domains\QuestionBank\Contracts\QuestionManagementService;
use App\Domains\QuestionBank\Listeners\RecalculatePsychometricsListener;
use App\Domains\QuestionBank\Models\Question;
use App\Domains\QuestionBank\Models\QuestionBank;
use App\Domains\QuestionBank\Policies\QuestionBankPolicy;
use App\Domains\QuestionBank\Policies\QuestionPolicy;
use App\Domains\QuestionBank\Services\CategoryTreeServiceImpl;
use App\Domains\QuestionBank\Services\QuestionBankServiceImpl;
use App\Domains\QuestionBank\Services\QuestionManagementServiceImpl;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class QuestionBankServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(QuestionBankService::class, QuestionBankServiceImpl::class);
        $this->app->bind(CategoryTreeService::class, CategoryTreeServiceImpl::class);
        $this->app->bind(QuestionManagementService::class, QuestionManagementServiceImpl::class);
    }

    public function boot(): void
    {
        Gate::policy(QuestionBank::class, QuestionBankPolicy::class);
        Gate::policy(Question::class, QuestionPolicy::class);

        Event::listen(ExamSessionCompleted::class, [RecalculatePsychometricsListener::class, 'handle']);
    }
}
