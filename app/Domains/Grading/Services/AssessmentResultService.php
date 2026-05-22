<?php

declare(strict_types=1);

namespace App\Domains\Grading\Services;

use App\Domains\Grading\DTOs\AssessmentResultView;
use App\Domains\Grading\DTOs\AssessmentSummary;
use App\Domains\Grading\Models\AssessmentResult;
use App\Domains\Grading\Models\Grade;
use App\Domains\Grading\Repositories\AssessmentResultRepository;
use App\Domains\Grading\Repositories\GradeRepository;
use DateTimeImmutable;
use DateTimeInterface;

class AssessmentResultService
{
    public function __construct(
        private readonly AssessmentResultRepository $results,
        private readonly GradeRepository $grades,
    ) {
    }

    public function getForSession(string $sessionId): ?AssessmentResultView
    {
        $result = $this->results->findBySession($sessionId);

        if ($result === null) {
            return null;
        }

        $grade = $this->grades->findBySession($sessionId);

        return new AssessmentResultView(
            resultId: (string) $result->result_id,
            sessionId: (string) $result->session_id,
            candidateId: (string) $result->candidate_user_id,
            examId: (string) $result->exam_id,
            tenantId: (string) $result->tenant_id,
            resultStatus: (string) $result->result_status,
            publicationStatus: (string) $result->publication_status,
            resultCalculatedAt: $this->toDateTime($result->result_calculated_at),
            publishedAt: $this->toDateTime($result->published_at),
            summary: $grade !== null ? $this->buildSummary($result, $grade) : null,
            resultMetadata: is_array($result->result_metadata) ? $result->result_metadata : [],
        );
    }

    private function buildSummary(AssessmentResult $result, Grade $grade): AssessmentSummary
    {
        $metadata = is_array($grade->grading_metadata) ? $grade->grading_metadata : [];

        return new AssessmentSummary(
            sessionId: (string) $result->session_id,
            candidateId: (string) $result->candidate_user_id,
            examId: (string) $result->exam_id,
            tenantId: (string) $result->tenant_id,
            rawScore: (float) $grade->raw_score,
            maxScore: (float) ($metadata['max_score'] ?? 0.0),
            percentage: (float) $grade->normalized_score,
            gradeLetter: (string) ($grade->grade_letter ?? 'N/A'),
            isPassing: (bool) $grade->is_passing_grade,
            isFinal: (bool) $grade->is_final_grade,
            totalEvaluations: (int) ($metadata['total_evaluations'] ?? 0),
            pendingEvaluations: (int) ($metadata['pending_evaluations'] ?? 0),
            correctCount: (int) ($metadata['correct_count'] ?? 0),
            incorrectCount: (int) ($metadata['incorrect_count'] ?? 0),
            breakdown: is_array($metadata['breakdown'] ?? null) ? $metadata['breakdown'] : [],
        );
    }

    private function toDateTime(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        return new DateTimeImmutable((string) $value);
    }
}
