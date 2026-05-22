<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Providers;

use App\Domains\ExamSession\Events\ExamSessionCompleted;
use App\Domains\QuestionBank\Contracts\QuestionBankService;
use App\Domains\QuestionBank\Listeners\RecalculatePsychometricsListener;
use App\Domains\QuestionBank\Services\QuestionBankServiceImpl;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class QuestionBankServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(QuestionBankService::class, QuestionBankServiceImpl::class);
    }

    public function boot(): void
    {
        Event::listen(ExamSessionCompleted::class, [RecalculatePsychometricsListener::class, 'handle']);
    }
}
