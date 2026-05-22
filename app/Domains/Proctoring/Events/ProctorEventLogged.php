<?php

declare(strict_types=1);

namespace App\Domains\Proctoring\Events;

use DateTimeImmutable;

final readonly class ProctorEventLogged
{
    /**
     * Pure DTO event — no Eloquent models embedded, queue-safe.
     */
    public function __construct(
        public string $eventId,
        public string $sessionId,
        public string $candidateId,
        public string $tenantId,
        public string $eventType,
        public string $eventCategory,
        public ?string $severityLevel,
        public ?float $detectionConfidenceScore,
        public array $eventPayload,
        public DateTimeImmutable $eventTimestamp,
    ) {
    }
}
