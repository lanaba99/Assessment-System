<?php

declare(strict_types=1);

namespace App\Domains\Penalties\Listeners;

use App\Domains\Penalties\Services\PenaltyEvaluationService;
use App\Domains\Proctoring\Events\ProctorEventLogged;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ApplyPenaltyOnProctorEventListener implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'penalties';

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        private readonly PenaltyEvaluationService $service,
    ) {
    }

    public function handle(ProctorEventLogged $event): void
    {
        $this->service->evaluateProctorEvent($event);
    }
}
