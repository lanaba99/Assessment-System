<?php

declare(strict_types=1);

namespace App\Domains\Grading\Services;

use App\Domains\Grading\Repositories\CompetencyScoreRepository;
use Illuminate\Support\Facades\DB;

class CompetencyAggregationService
{
    private const DEFAULT_TARGET_PERCENTAGE = 70.0;

    private const ON_TARGET_TOLERANCE = 5.0;

    public function __construct(
        private readonly CompetencyScoreRepository $scores,
    ) {
    }

    public function aggregateForFinalGrade(string $tenantId, string $sessionId, string $candidateId): void
    {
        $rows = DB::table('question_responses as qr')
            ->join('question_versions as qv', 'qv.version_id', '=', 'qr.question_version_id')
            ->join('questions as q', 'q.question_id', '=', 'qv.question_id')
            ->join('question_competency_weights as qcw', 'qcw.question_id', '=', 'q.question_id')
            ->where('qr.tenant_id', $tenantId)
            ->where('qr.session_id', $sessionId)
            ->where('qr.candidate_user_id', $candidateId)
            ->where('q.tenant_id', $tenantId)
            ->select([
                'qcw.competency_id',
                'qcw.weight_percentage',
                'qr.response_id',
                'qr.final_score',
                'qr.normalized_score',
                'qr.raw_score',
                'qr.is_correct',
            ])
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $aggregates = [];

        foreach ($rows as $row) {
            $competencyId = (string) $row->competency_id;
            $weight = max(0.0, (float) $row->weight_percentage);
            $scorePercentage = $this->scorePercentage($row);

            $aggregates[$competencyId] ??= [
                'weighted_score_total' => 0.0,
                'weight_total' => 0.0,
                'response_count' => 0,
                'response_ids' => [],
            ];

            $aggregates[$competencyId]['weighted_score_total'] += $scorePercentage * $weight;
            $aggregates[$competencyId]['weight_total'] += $weight;
            $aggregates[$competencyId]['response_count']++;
            $aggregates[$competencyId]['response_ids'][] = (string) $row->response_id;
        }

        foreach ($aggregates as $competencyId => $data) {
            $weightTotal = (float) $data['weight_total'];
            $scoreAchieved = $weightTotal > 0.0
                ? round(((float) $data['weighted_score_total']) / $weightTotal, 2)
                : 0.0;

            $gapPercentage = round($scoreAchieved - self::DEFAULT_TARGET_PERCENTAGE, 2);

            $this->scores->upsertForSession(
                tenantId: $tenantId,
                sessionId: $sessionId,
                competencyId: (string) $competencyId,
                candidateId: $candidateId,
                attributes: [
                    'score_achieved' => $scoreAchieved,
                    'score_target' => self::DEFAULT_TARGET_PERCENTAGE,
                    'score_maximum' => $weightTotal,
                    'proficiency_level_achieved' => $this->proficiencyLevel($scoreAchieved),
                    'gap_percentage' => $gapPercentage,
                    'gap_status' => $this->gapStatus($gapPercentage),
                    'score_metadata' => [
                        'source' => 'question_response_competency_weights',
                        'weighted_score_total' => round((float) $data['weighted_score_total'], 4),
                        'weight_total' => round($weightTotal, 4),
                        'response_count' => (int) $data['response_count'],
                        'response_ids' => array_values(array_unique($data['response_ids'])),
                    ],
                ],
            );
        }
    }

    private function scorePercentage(object $row): float
    {
        foreach (['final_score', 'normalized_score', 'raw_score'] as $field) {
            if ($row->{$field} !== null) {
                return max(0.0, min(100.0, (float) $row->{$field}));
            }
        }

        if ($row->is_correct === null) {
            return 0.0;
        }

        return (bool) $row->is_correct ? 100.0 : 0.0;
    }

    private function proficiencyLevel(float $scorePercentage): int
    {
        return match (true) {
            $scorePercentage >= 80.0 => 5,
            $scorePercentage >= 60.0 => 4,
            $scorePercentage >= 40.0 => 3,
            $scorePercentage >= 20.0 => 2,
            default => 1,
        };
    }

    private function gapStatus(float $gapPercentage): string
    {
        return match (true) {
            $gapPercentage >= self::ON_TARGET_TOLERANCE => 'above_target',
            $gapPercentage <= -self::ON_TARGET_TOLERANCE => 'below_target',
            default => 'on_target',
        };
    }
}
