<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Repositories;

use App\Domains\ExamSession\Models\QuestionResponse;
use App\Domains\QuestionBank\Models\Question;
use App\Domains\QuestionBank\Models\QuestionPsychometrics;
use App\Domains\QuestionBank\Models\QuestionVersion;
use Illuminate\Support\Collection;

class QuestionBankRepository
{
    public function __construct(
        private readonly Question $question,
        private readonly QuestionVersion $version,
        private readonly QuestionPsychometrics $psychometrics,
        private readonly QuestionResponse $response,
    ) {
    }

    public function findEligibleVersionsForCompetencies(
        string $tenantId,
        array $competencyIds,
        array $excludedVersionIds,
        bool $requireCalibrated,
    ): Collection {
        $query = $this->version
            ->newQuery()
            ->with(['question.competencies', 'psychometrics'])
            ->whereHas('question', function ($q) use ($tenantId, $competencyIds): void {
                $q->where('tenant_id', $tenantId)
                    ->where('is_archived', false)
                    ->where('is_deprecated', false)
                    ->whereHas('competencies', function ($cq) use ($competencyIds): void {
                        $cq->whereIn('competencies.competency_id', $competencyIds);
                    });
            })
            ->where('approval_status', 'approved');

        if ($excludedVersionIds !== []) {
            $query->whereNotIn('version_id', $excludedVersionIds);
        }

        if ($requireCalibrated) {
            $query->whereHas('psychometrics', function ($pq): void {
                $pq->where('is_calibrated', true);
            });
        }

        return $query->get();
    }

    public function findVersionIdsAdministeredSince(string $candidateId, string $sinceIso): array
    {
        return $this->response
            ->newQuery()
            ->where('candidate_user_id', $candidateId)
            ->where('response_submitted_at', '>=', $sinceIso)
            ->pluck('question_version_id')
            ->unique()
            ->values()
            ->all();
    }

    public function findVersionWithPsychometrics(string $versionId): ?QuestionVersion
    {
        return $this->version
            ->newQuery()
            ->with(['psychometrics', 'question.competencies'])
            ->find($versionId);
    }

    public function countCalibratedByCompetency(string $tenantId, array $competencyIds): array
    {
        return $this->version
            ->newQuery()
            ->join('question_competency_weights as qcw', 'qcw.question_id', '=', 'question_versions.question_id')
            ->join('questions as q', 'q.question_id', '=', 'question_versions.question_id')
            ->join('question_psychometrics as pm', 'pm.question_version_id', '=', 'question_versions.version_id')
            ->where('q.tenant_id', $tenantId)
            ->where('q.is_archived', false)
            ->where('q.is_deprecated', false)
            ->where('pm.is_calibrated', true)
            ->whereIn('qcw.competency_id', $competencyIds)
            ->groupBy('qcw.competency_id')
            ->selectRaw('qcw.competency_id, COUNT(DISTINCT question_versions.version_id) as item_count')
            ->pluck('item_count', 'competency_id')
            ->all();
    }

    public function countCalibratedByBloomLevel(string $tenantId, array $bloomLevels): array
    {
        return $this->version
            ->newQuery()
            ->join('questions as q', 'q.question_id', '=', 'question_versions.question_id')
            ->join('question_psychometrics as pm', 'pm.question_version_id', '=', 'question_versions.version_id')
            ->where('q.tenant_id', $tenantId)
            ->where('q.is_archived', false)
            ->where('q.is_deprecated', false)
            ->where('pm.is_calibrated', true)
            ->whereIn('q.cognitive_level', $bloomLevels)
            ->groupBy('q.cognitive_level')
            ->selectRaw('q.cognitive_level, COUNT(DISTINCT question_versions.version_id) as item_count')
            ->pluck('item_count', 'cognitive_level')
            ->all();
    }

    public function fetchResponsesForCalibration(string $questionVersionId): Collection
    {
        return $this->response
            ->newQuery()
            ->select(['response_id', 'session_id', 'is_correct', 'final_score'])
            ->where('question_version_id', $questionVersionId)
            ->whereNotNull('is_correct')
            ->get();
    }

    public function fetchSessionTotalScores(array $sessionIds): array
    {
        if ($sessionIds === []) {
            return [];
        }

        return $this->response
            ->newQuery()
            ->whereIn('session_id', $sessionIds)
            ->groupBy('session_id')
            ->selectRaw('session_id, SUM(COALESCE(final_score, 0)) as total_score, COUNT(*) as item_count')
            ->get()
            ->keyBy('session_id')
            ->map(static fn ($row): array => [
                'total_score' => (float) $row->total_score,
                'item_count' => (int) $row->item_count,
            ])
            ->all();
    }

    public function findPsychometricsBelowDiscrimination(string $tenantId, float $threshold): Collection
    {
        return $this->psychometrics
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('is_calibrated', true)
            ->where('discrimination_index', '<', $threshold)
            ->get();
    }

    public function upsertPsychometrics(array $attributes): QuestionPsychometrics
    {
        $record = $this->psychometrics
            ->newQuery()
            ->firstOrNew(['question_version_id' => $attributes['question_version_id']]);

        $record->fill($attributes)->save();

        return $record;
    }
}
