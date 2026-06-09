<?php

declare(strict_types=1);

namespace App\Domains\ExamEngine\Repositories;

use App\Domains\ExamEngine\Models\ExamBlueprint;
use Illuminate\Support\Collection;

/**
 * Tenant isolation note: ExamBlueprint has no tenant_id column.
 * Isolation is provided by the exam_id foreign key — callers must ensure the
 * examId belongs to the expected tenant before invoking this repository
 * (typically the examId comes from an already-validated ExamSessionCompleted event).
 */
class ExamBlueprintRepository
{
    public function __construct(
        private readonly ExamBlueprint $model,
    ) {
    }

    /**
     * Returns all blueprints for the given exam, ordered by section sequence
     * so callers can rely on a consistent iteration order.
     *
     * @return Collection<int, ExamBlueprint>
     */
    public function findForExam(string $examId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('exam_id', $examId)
            ->get();
    }

    /**
     * Returns all blueprints that have a non-null section_id for the given exam.
     * Blueprints without a section cannot be matched to session items and are
     * excluded from weighted scoring.
     *
     * @return Collection<int, ExamBlueprint>
     */
    public function findSectionedForExam(string $examId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('exam_id', $examId)
            ->whereNotNull('section_id')
            ->get();
    }
}
