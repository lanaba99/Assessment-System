<?php

declare(strict_types=1);

namespace App\Domains\Penalties\Listeners;

use App\Domains\Penalties\Services\PenaltyEvaluationService;
use App\Domains\Proctoring\Events\ProctorEventLogged;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;

class ApplyPenaltyOnProctorEventListener implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        private readonly PenaltyEvaluationService $service,
    ) {
        $this->onQueue('penalties');
    }

    public function handle(ProctorEventLogged $event): void
    {
        $this->service->evaluateProctorEvent($event);
    }
}
