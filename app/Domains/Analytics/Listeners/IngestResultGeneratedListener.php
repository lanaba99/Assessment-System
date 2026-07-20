<?php

declare(strict_types=1);

namespace App\Domains\Analytics\Listeners;

use App\Domains\Analytics\Services\AnalyticsIngestionService;
use App\Domains\Grading\Events\ResultGenerated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

use Illuminate\Bus\Queueable;

class IngestResultGeneratedListener implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;


    public int $tries = 3;

    public function __construct(
        private readonly AnalyticsIngestionService $ingestion,
    ) {
            $this->onQueue('analytics');

    }

    public function handle(ResultGenerated $event): void
    {
        $this->ingestion->ingest($event);
    }
}
