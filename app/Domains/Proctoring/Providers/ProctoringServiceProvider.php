<?php

declare(strict_types=1);

namespace App\Domains\Proctoring\Providers;

use App\Domains\Proctoring\Contracts\ProctoringService;
use App\Domains\Proctoring\Events\ProctorEventLogged;
use App\Domains\Proctoring\Models\ProctorLog;
use App\Domains\Proctoring\Policies\ProctoringPolicy;
use App\Domains\Proctoring\Services\ProctoringServiceImpl;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Auto-registered by App\Providers\DomainServiceProvider.
 */
class ProctoringServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProctoringService::class, ProctoringServiceImpl::class);
    }

    public function boot(): void
    {
        Gate::policy(ProctorLog::class, ProctoringPolicy::class);

        ProctorLog::created(static function (ProctorLog $log): void {
            $timestamp = $log->event_timestamp;

            if ($timestamp instanceof DateTimeInterface && ! $timestamp instanceof DateTimeImmutable) {
                $timestamp = DateTimeImmutable::createFromInterface($timestamp);
            }

            $payload = new ProctorEventLogged(
                eventId: (string) $log->event_id,
                sessionId: (string) $log->session_id,
                candidateId: (string) $log->candidate_user_id,
                tenantId: (string) $log->tenant_id,
                eventType: (string) $log->event_type,
                eventCategory: (string) $log->event_category,
                severityLevel: $log->severity_level !== null ? (string) $log->severity_level : null,
                detectionConfidenceScore: $log->detection_confidence_score !== null
                    ? (float) $log->detection_confidence_score
                    : null,
                eventPayload: is_array($log->event_payload) ? $log->event_payload : [],
                eventTimestamp: $timestamp instanceof DateTimeImmutable
                    ? $timestamp
                    : new DateTimeImmutable(),
            );

            // Delay the event dispatch until after the current database transaction
            // commits. This prevents the queued ApplyPenaltyOnProctorEventListener
            // from processing an event whose ProctorLog row was rolled back.
            // If no transaction is active, DB::afterCommit() runs the callback
            // immediately — behaviour is unchanged for non-transactional writes.
            DB::afterCommit(static fn () => event($payload));
        });
    }
}