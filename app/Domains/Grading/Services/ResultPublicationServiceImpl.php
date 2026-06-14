<?php

declare(strict_types=1);

namespace App\Domains\Grading\Services;

use App\Domains\Grading\Contracts\ResultPublicationService;
use App\Domains\Grading\DTOs\AssessmentResultView;
use App\Domains\Grading\DTOs\AssessmentSummary;
use App\Domains\Grading\Exceptions\AssessmentResultNotFoundException;
use App\Domains\Grading\Exceptions\PenaltyProcessingPendingException;
use App\Domains\Grading\Exceptions\ResultNotFinalizedException;
use App\Domains\Grading\Exceptions\WorkflowNotApprovedException;
use App\Domains\Grading\Models\AssessmentResult;
use App\Domains\Grading\Repositories\AssessmentResultRepository;
use App\Domains\Grading\Repositories\GradeRepository;
use App\Domains\Workflows\Services\ApprovalWorkflowService;
use Illuminate\Support\Facades\DB;

class ResultPublicationServiceImpl implements ResultPublicationService
{
    public function __construct(
        private readonly AssessmentResultRepository $results,
        private readonly GradeRepository $grades,
        private readonly AssessmentResultServiceImpl $resultViews,
        private readonly PenaltyApplicationService $penalties,
        private readonly ApprovalWorkflowService $workflows,
    ) {
    }

    public function publishSessionResult(
        string $tenantId,
        string $sessionId,
        ?string $publishedByUserId = null,
    ): AssessmentResultView
    {
        /** @var AssessmentResult $result */
        $result = DB::transaction(function () use ($tenantId, $sessionId, $publishedByUserId): AssessmentResult {
            $result = $this->results->lockBySessionForPublication($tenantId, $sessionId);

            if ($result === null) {
                throw AssessmentResultNotFoundException::forSession($sessionId);
            }

            $grade = $this->grades->findBySession($tenantId, $sessionId);

            if (
                $result->result_status !== AssessmentSummary::STATUS_FINAL
                || $grade === null
                || ! $grade->is_final_grade
            ) {
                throw ResultNotFinalizedException::forSession($sessionId);
            }

            if ($this->penalties->hasPendingProcessing($tenantId, $sessionId)) {
                throw PenaltyProcessingPendingException::forSession($sessionId);
            }

            if (! $this->workflows->isPublicationApprovedForResult($tenantId, (string) $result->result_id)) {
                throw WorkflowNotApprovedException::forSession($sessionId);
            }

            return $this->results->publish($result, $publishedByUserId);
        });

        $grade = $this->grades->findBySession($tenantId, $sessionId);

        return $this->resultViews->viewFromModels($result, $grade);
    }
}
