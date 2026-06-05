<?php

declare(strict_types=1);

namespace App\Domains\ExamEngine\Providers;

use App\Domains\ExamEngine\Contracts\ExamEngineService;
use App\Domains\ExamEngine\Contracts\QuestionSelectionService;
use App\Domains\ExamEngine\Models\Exam;
use App\Domains\ExamEngine\Policies\ExamPolicy;
use App\Domains\ExamEngine\Services\ExamEngineServiceImpl;
use App\Domains\ExamEngine\Services\QuestionSelectionServiceImpl;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Auto-registered by App\Providers\DomainServiceProvider, which scans each
 * domain's Providers directory. No manual registration in
 * bootstrap/providers.php is required.
 */
class ExamEngineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ExamEngineService::class, ExamEngineServiceImpl::class);
        $this->app->bind(QuestionSelectionService::class, QuestionSelectionServiceImpl::class);
    }

    public function boot(): void
    {
        Gate::policy(Exam::class, ExamPolicy::class);
    }
}
