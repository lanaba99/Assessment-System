<?php

declare(strict_types=1);

namespace App\Domains\ExamEngine\Repositories;

use App\Domains\ExamEngine\Models\Exam;
use Illuminate\Support\Collection;

/**
 * Tenant isolation is enforced by the BelongsToTenant global scope on the
 * Exam model. The explicit where('tenant_id') calls below mirror the Competency
 * repository's belt-and-suspenders approach: if a connection ever leaks, rows
 * with the wrong tenant_id fail here before reaching the application.
 *
 * Writes use forceCreate/forceFill so server-controlled columns (tenant_id,
 * created_by_user_id) persist despite not being in $fillable.
 */
class ExamRepository
{
    public function __construct(
        private readonly Exam $model,
    ) {
    }

    /**
     * @return Collection<int, Exam>
     */
    public function allForTenant(string $tenantId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findById(string $tenantId, string $examId): ?Exam
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->whereKey($examId)
            ->first();
    }

    /**
     * Session-start variant: relies on the BelongsToTenant global scope for
     * tenant isolation (always set in a tenant HTTP context). Eager-loads
     * sections ordered by sequence + their blueprints.
     */
    public function findWithSectionsAndBlueprintsForSession(string $examId): ?Exam
    {
        return $this->model
            ->newQuery()
            ->whereKey($examId)
            ->with([
                'sections' => static fn ($q) => $q->orderBy('section_sequence'),
                'sections.blueprints',
            ])
            ->first();
    }

    public function findWithSectionsAndBlueprints(string $tenantId, string $examId): ?Exam
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->whereKey($examId)
            ->with(['sections', 'sections.blueprints'])
            ->first();
    }

    public function existsByCode(string $tenantId, string $examCode): bool
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('exam_code', $examCode)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Exam
    {
        return $this->model->newQuery()->forceCreate($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Exam $exam, array $attributes): Exam
    {
        $exam->forceFill($attributes)->save();

        return $exam;
    }

    public function delete(Exam $exam): void
    {
        $exam->delete();
    }
}
