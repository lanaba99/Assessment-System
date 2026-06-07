<?php

declare(strict_types=1);

namespace App\Domains\Proctoring\DTOs;

final readonly class LogProctorEventCommand
{
    public function __construct(
        public string $tenantId,
        public string $sessionId,
        // The authenticated actor's id — used to distinguish candidate-sourced
        // events (actor == session candidate) from proctor-sourced ones.
        public string $actorId,
        public string $eventType,
        public string $eventTimestamp,
        public ?string $eventCategory = null,
        public ?array $eventPayload = null,
        public string $severityLevel = 'info',
        public ?float $detectionConfidenceScore = null,
        public ?string $screenshotUrl = null,
        public ?string $videoSegmentUrl = null,
        public ?array $detectionParameters = null,
    ) {
    }
}
