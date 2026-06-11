<?php

declare(strict_types=1);

namespace App\Domains\Grading\Contracts;

use App\Domains\Grading\DTOs\AssessmentResultView;

interface AssessmentResultService
{
    /**
     * Build the read-side view DTO for a session's assessment result.
     *
     * Returns null when no result has been calculated yet (e.g. the session
     * has not completed or grading is still in progress).
     *
     * Tenant isolation is enforced here: the result is only returned when
     * it belongs to the given tenant.
     */
    public function getForSession(string $tenantId, string $sessionId): ?AssessmentResultView;

    /**
     * Candidate-facing read path.
     *
     * Returns null unless the result belongs to the tenant, belongs to the
     * candidate, and has been explicitly published.
     */
    public function getPublishedForCandidateSession(
        string $tenantId,
        string $sessionId,
        string $candidateId,
    ): ?AssessmentResultView;
}
