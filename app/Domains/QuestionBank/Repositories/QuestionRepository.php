<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Repositories;

use App\Domains\QuestionBank\Models\Question;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tenant isolation is enforced by the BelongsToTenant global scope on the
 * model (audit Option A), so no method here threads or filters `tenant_id` by
 * hand. Soft-deleted rows are likewise hidden by the SoftDeletes scope.
 */
class QuestionRepository
{
    public function __construct(
        private readonly Question $model,
    ) {
    }

    public function findById(string $questionId): ?Question
    {
        return $this->model
            ->newQuery()
            ->whereKey($questionId)
            ->first();
    }

    public function findByIdWithDetails(string $questionId): ?Question
    {
        return $this->model
            ->newQuery()
            ->whereKey($questionId)
            ->with([
                'currentVersion.options',
                'currentVersion.psychometrics',
                'category',
            ])
            ->first();
    }

    /**
     * @param  array{category_id?: string, bloom_level?: int, type?: string}  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model
            ->newQuery()
            ->with(['category', 'currentVersion.psychometrics']);

        $this->applyFilters($query, $filters);

        return $query
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Question
    {
        // Trusted boundary: $attributes is built by the service from validated
        // input, so forceCreate writes server-controlled columns (e.g.
        // created_by_user_id, total_usage_count) that are intentionally not $fillable.
        return $this->model->newQuery()->forceCreate($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Question $question, array $attributes): Question
    {
        // forceFill so server-controlled columns (e.g. current_version_id) can
        // be written from the trusted service layer.
        $question->forceFill($attributes)->save();

        return $question;
    }

    /**
     * Soft delete — the SoftDeletes trait on the model turns this into a
     * `deleted_at` stamp, preserving versions/responses that reference it.
     */
    public function delete(Question $question): void
    {
        $question->delete();
    }

    /**
     * @param  Builder<Question>  $query
     * @param  array{category_id?: string, bloom_level?: int, type?: string}  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['bloom_level'])) {
            $query->where('cognitive_level', $filters['bloom_level']);
        }

        if (isset($filters['type'])) {
            $query->where('question_type', $filters['type']);
        }
    }
}
