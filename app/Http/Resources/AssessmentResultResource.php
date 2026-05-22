<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Grading\DTOs\AssessmentResultView;
use App\Domains\Grading\DTOs\AssessmentSummary;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AssessmentResultView $resource
 */
class AssessmentResultResource extends JsonResource
{
    public static $wrap = 'data';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $view = $this->resource;

        return [
            'result_id' => $view->resultId,
            'session_id' => $view->sessionId,
            'candidate_id' => $view->candidateId,
            'exam_id' => $view->examId,
            'tenant_id' => $view->tenantId,
            'status' => [
                'result_status' => $view->resultStatus,
                'publication_status' => $view->publicationStatus,
            ],
            'summary' => $this->serializeSummary($view->summary),
            'timestamps' => [
                'calculated_at' => $view->resultCalculatedAt?->format(DateTimeInterface::ATOM),
                'published_at' => $view->publishedAt?->format(DateTimeInterface::ATOM),
            ],
            'metadata' => $view->resultMetadata,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeSummary(?AssessmentSummary $summary): ?array
    {
        if ($summary === null) {
            return null;
        }

        return [
            'raw_score' => $summary->rawScore,
            'max_score' => $summary->maxScore,
            'percentage' => $summary->percentage,
            'grade_letter' => $summary->gradeLetter,
            'is_passing' => $summary->isPassing,
            'is_final' => $summary->isFinal,
            'totals' => [
                'evaluations' => $summary->totalEvaluations,
                'pending_evaluations' => $summary->pendingEvaluations,
                'correct' => $summary->correctCount,
                'incorrect' => $summary->incorrectCount,
            ],
            'breakdown' => $summary->breakdown,
        ];
    }
}
