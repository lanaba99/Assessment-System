<?php

declare(strict_types=1);

namespace App\Domains\Grading\Contracts;

use App\Domains\Grading\DTOs\AssessmentResultView;

interface ResultPublicationService
{
    /**
     * Publish a finalized assessment result for candidate visibility.
     *
     * The operation is tenant-scoped and idempotent: publishing an already
     * published result returns the current view without mutating published_at.
     */
    public function publishSessionResult(
        string $tenantId,
        string $sessionId,
        ?string $publishedByUserId = null,
    ): AssessmentResultView;
}
