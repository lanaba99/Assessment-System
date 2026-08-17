<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Domains\Grading\Models\AssessmentResult;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

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
            'exam' => \App\Domains\ExamEngine\Models\Exam::class, 
        ]);

        $this->configureRateLimiting();
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
            $identifier = $actor !== null ? "user:{$actor->id}" : 'ip:' . $request->ip();

            return Limit::perMinute(240)->by("{$tenantId}:{$identifier}");
        });
    }
}