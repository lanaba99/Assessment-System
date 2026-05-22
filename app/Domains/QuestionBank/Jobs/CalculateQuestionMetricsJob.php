<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Jobs;

use App\Domains\QuestionBank\Services\PsychometricAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CalculateQuestionMetricsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public string $queue = 'psychometrics';

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly string $sessionId,
    ) {
    }

    public function handle(PsychometricAnalysisService $service): void
    {
        $service->analyzeSession($this->sessionId);
    }
}
