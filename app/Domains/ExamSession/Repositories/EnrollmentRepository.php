<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\Repositories;

use App\Domains\ExamSession\Models\ExamCandidateEligible;
use Illuminate\Support\Collection;

/**
 * Tenant isolation is enforced by explicit where('tenant_id') on every query.
 * ExamCandidateEligible uses AutoFillsTenantId (no global scope), so this
 * repository is the primary isolation layer for enrollment data.
 */
class EnrollmentRepository
{
    public function __construct(
        private readonly ExamCandidateEligible $model,
    ) {
    }

    public function findById(string $tenantId, string $enrollmentId): ?ExamCandidateEligible
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->whereKey($enrollmentId)
            ->first();
    }

    public function findByCandidateAndExam(
        string $tenantId,
        string $candidateUserId,
        string $examId,
    ): ?ExamCandidateEligible {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('candidate_user_id', $candidateUserId)
            ->where('exam_id', $examId)
            ->first();
    }

    /**
     * @return Collection<int, ExamCandidateEligible>
     */
    public function listForExam(string $tenantId, string $examId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('exam_id', $examId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ExamCandidateEligible
    {
        return $this->model->newQuery()->forceCreate($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(ExamCandidateEligible $enrollment, array $attributes): ExamCandidateEligible
    {
        $enrollment->forceFill($attributes)->save();

        return $enrollment;
    }
}
