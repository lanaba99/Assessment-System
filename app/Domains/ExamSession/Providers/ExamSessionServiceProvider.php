<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\Providers;

use App\Domains\ExamSession\Contracts\EnrollmentService;
use App\Domains\ExamSession\Contracts\ExamSessionService;
use App\Domains\ExamSession\Models\CandidateExamStatus;
use App\Domains\ExamSession\Models\ExamCandidateEligible;
use App\Domains\ExamSession\Policies\ExamSessionPolicy;
use App\Domains\ExamSession\Services\EnrollmentServiceImpl;
use App\Domains\ExamSession\Services\ExamSessionServiceImpl;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Auto-registered by App\Providers\DomainServiceProvider, which scans each
 * domain's Providers directory. No manual registration in
 * bootstrap/providers.php is required.
 */
class ExamSessionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ExamSessionService::class, ExamSessionServiceImpl::class);
        $this->app->bind(EnrollmentService::class, EnrollmentServiceImpl::class);
    }

    public function boot(): void
    {
        Gate::policy(CandidateExamStatus::class, ExamSessionPolicy::class);
        // ExamCandidateEligible (enrollments) uses the same policy for the
        // manageEnrollments ability so the EnrollmentController can authorize
        // class-level actions through the unified ExamSession gate.
        Gate::policy(ExamCandidateEligible::class, ExamSessionPolicy::class);
    }
}
