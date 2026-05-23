<?php

declare(strict_types=1);

namespace App\Domains\Competency\Providers;

use App\Domains\Competency\Contracts\CompetencyFrameworkRepository;
use App\Domains\Competency\Contracts\CompetencyFrameworkService;
use App\Domains\Competency\Contracts\CompetencyRepository;
use App\Domains\Competency\Contracts\CompetencyService;
use App\Domains\Competency\Contracts\ProficiencyLevelRepository;
use App\Domains\Competency\Contracts\ProficiencyLevelService;
use App\Domains\Competency\Repositories\EloquentCompetencyFrameworkRepository;
use App\Domains\Competency\Repositories\EloquentCompetencyRepository;
use App\Domains\Competency\Repositories\EloquentProficiencyLevelRepository;
use App\Domains\Competency\Services\CompetencyFrameworkServiceImpl;
use App\Domains\Competency\Services\CompetencyServiceImpl;
use App\Domains\Competency\Services\ProficiencyLevelServiceImpl;
use Illuminate\Support\ServiceProvider;

class CompetencyServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    private const REPOSITORY_BINDINGS = [
        CompetencyFrameworkRepository::class => EloquentCompetencyFrameworkRepository::class,
        CompetencyRepository::class => EloquentCompetencyRepository::class,
        ProficiencyLevelRepository::class => EloquentProficiencyLevelRepository::class,
    ];

    /**
     * @var array<class-string, class-string>
     */
    private const SERVICE_BINDINGS = [
        CompetencyFrameworkService::class => CompetencyFrameworkServiceImpl::class,
        CompetencyService::class => CompetencyServiceImpl::class,
        ProficiencyLevelService::class => ProficiencyLevelServiceImpl::class,
    ];

    public function register(): void
    {
        foreach (self::REPOSITORY_BINDINGS as $contract => $implementation) {
            $this->app->bind($contract, $implementation);
        }

        foreach (self::SERVICE_BINDINGS as $contract => $implementation) {
            $this->app->bind($contract, $implementation);
        }
    }
}
