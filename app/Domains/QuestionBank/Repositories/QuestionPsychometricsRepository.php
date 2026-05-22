<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Repositories;

use App\Domains\QuestionBank\DTOs\ItemPsychometrics;
use App\Domains\QuestionBank\Models\QuestionPsychometrics;

class QuestionPsychometricsRepository
{
    public function __construct(
        private readonly QuestionPsychometrics $model,
    ) {
    }

    public function findByVersionId(string $versionId): ?QuestionPsychometrics
    {
        return $this->model
            ->newQuery()
            ->where('question_version_id', $versionId)
            ->first();
    }

    /**
     * Pessimistic lock for atomic upsert. Caller must be inside a DB transaction.
     */
    public function findByVersionIdForUpdate(string $versionId): ?QuestionPsychometrics
    {
        return $this->model
            ->newQuery()
            ->where('question_version_id', $versionId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * @param  array<int, string>  $versionIds
     * @return array<string, array<string, mixed>>  keyed by question_version_id
     */
    public function getByVersionIds(array $versionIds): array
    {
        if ($versionIds === []) {
            return [];
        }

        $rows = $this->model
            ->newQuery()
            ->whereIn('question_version_id', $versionIds)
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->question_version_id] = [
                'difficulty_index' => $row->difficulty_index !== null ? (float) $row->difficulty_index : null,
                'discrimination_index' => $row->discrimination_index !== null ? (float) $row->discrimination_index : null,
                'point_biserial' => $row->point_biserial !== null ? (float) $row->point_biserial : null,
                'sample_size' => (int) $row->sample_size,
                'correct_count' => (int) $row->correct_count,
                'is_calibrated' => (bool) $row->is_calibrated,
                'calibration_status' => (string) $row->calibration_status,
            ];
        }

        return $map;
    }

    public function upsert(string $tenantId, ItemPsychometrics $metrics): QuestionPsychometrics
    {
        $attributes = [
            'tenant_id' => $tenantId,
            'difficulty_index' => $metrics->difficultyIndex,
            'discrimination_index' => $metrics->discriminationIndex,
            'point_biserial' => $metrics->pointBiserial,
            'sample_size' => $metrics->sampleSize,
            'correct_count' => $metrics->correctCount,
            'is_calibrated' => $metrics->isCalibrated,
            'calibration_status' => $metrics->calibrationStatus,
            'last_calibrated_at' => $metrics->lastCalibratedAt,
        ];

        $existing = $this->findByVersionIdForUpdate($metrics->questionVersionId);

        if ($existing !== null) {
            $existing->forceFill($attributes)->save();

            return $existing;
        }

        $attributes['question_version_id'] = $metrics->questionVersionId;

        return $this->model->newQuery()->create($attributes);
    }
}
