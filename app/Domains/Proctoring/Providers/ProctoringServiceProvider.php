<?php

declare(strict_types=1);

namespace App\Domains\Proctoring\Providers;

use App\Domains\Proctoring\Events\ProctorEventLogged;
use App\Domains\Proctoring\Models\ProctorLog;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\ServiceProvider;

class ProctoringServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        ProctorLog::created(static function (ProctorLog $log): void {
            $timestamp = $log->event_timestamp;
            if ($timestamp instanceof DateTimeInterface && ! $timestamp instanceof DateTimeImmutable) {
                $timestamp = DateTimeImmutable::createFromInterface($timestamp);
            }

            event(new ProctorEventLogged(
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
            ));
        });
    }
}
