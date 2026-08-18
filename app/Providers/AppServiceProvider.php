<?php

namespace App\Providers;

use App\Domains\ExamEngine\Models\Exam;
use App\Domains\Grading\Models\AssessmentResult;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'assessment_result' => AssessmentResult::class,
            'exam' => Exam::class,
        ]);

        $this->configureRateLimiting();
        $this->guardAgainstLogMailerOutsideLocalDevelopment();
        $this->logFailedQueueJobs();
        $this->forceHttpsInProduction();
    }

    /**
     * Password-reset tokens (and any other transactional mail) render their
     * plaintext content straight into whatever MAIL_MAILER writes to. The
     * "log" mailer is a normal, useful local/CI convenience — the docs
     * sandbox and CI both intentionally rely on it — but it means a real
     * usable reset token would land in storage/logs/laravel.log in any
     * environment where MAIL_MAILER isn't explicitly overridden. Fail loudly
     * and immediately at boot rather than quietly leaking tokens later.
     */
    private function guardAgainstLogMailerOutsideLocalDevelopment(): void
    {
        if (config('mail.default') !== 'log') {
            return;
        }

        if ($this->app->environment(['local', 'testing'])) {
            return;
        }

        throw new RuntimeException(
            'MAIL_MAILER=log is not allowed outside local/testing environments — it writes '
            .'plaintext content (including password-reset tokens) to storage/logs/laravel.log. '
            .'Set MAIL_MAILER to a real transport (smtp, ses, postmark, resend, ...) before '
            .'deploying to any other environment. See docs/SECURITY_BASELINE.md.',
        );
    }

    /**
     * No code path anywhere else in this app calls Log:: for failed jobs
     * (confirmed by audit — none of the 6 ShouldQueue listeners/jobs handle
     * their own failure visibility), so a failure in any queue currently
     * lands silently in failed_jobs with nothing surfacing it. This adds
     * the minimum safe visibility hook: log the job class, queue/connection,
     * and exception message — never the payload, since job payloads can
     * carry tenant/candidate data that has no business sitting in a log line.
     */
    private function logFailedQueueJobs(): void
    {
        $this->app['events']->listen(JobFailed::class, function (JobFailed $event): void {
            Log::error('Queued job failed', [
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'job' => $event->job->resolveName(),
                'exception' => $event->exception->getMessage(),
            ]);
        });
    }

    /**
     * Safe in every environment: APP_ENV is "local" for Sail/CI, so this is
     * a no-op there. Only forces https:// URL generation when running as
     * "production" — the app never enforces the scheme on incoming requests
     * (that's the reverse proxy/load balancer's job, see
     * docs/SECURITY_BASELINE.md), this only affects URLs the app itself
     * generates (route(), certificate verification links, etc.).
     */
    private function forceHttpsInProduction(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }

    /**
     * Phase 6 — API Contract, Versioning, Rate Limits, Idempotency.
     *
     * Global tenant-API limiter: 240 requests/minute, keyed by
     * (tenant, actor) — authenticated requests key on the user id;
     * unauthenticated requests (e.g. a bad/expired token hitting a
     * protected route) fall back to the client IP. Tenant is folded into
     * the key so the same user id or IP in two different tenant databases
     * never shares a bucket.
     *
     * This does NOT replace the tighter, purpose-specific throttles already
     * on auth endpoints (throttle.login, throttle:5,15 on MFA/password
     * reset/accept-invite) — those stay as-is and are intentionally
     * stricter than the general 240/min ceiling.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('tenant-api', function (Request $request) {
            $tenantId = function_exists('tenant') && tenant() !== null
                ? (string) tenant()->getKey()
                : 'no-tenant';

            $actor = $request->user();
            $identifier = $actor !== null ? "user:{$actor->id}" : 'ip:'.$request->ip();

            return Limit::perMinute(240)->by("{$tenantId}:{$identifier}");
        });
    }
}
