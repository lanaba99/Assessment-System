<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Repositories;

use App\Domains\QuestionBank\Models\Question;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class QuestionRepository
{
    public function __construct(
        private readonly Question $model,
    ) {
    }

    public function findById(string $tenantId, string $questionId): ?Question
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->whereKey($questionId)
            ->first();
    }

    public function findByIdWithDetails(string $tenantId, string $questionId): ?Question
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->whereKey($questionId)
            ->with([
                'currentVersion.options',
                'currentVersion.psychometrics',
                'bank',
            ])
            ->first();
    }

    /**
     * @param  array{category_id?: string, bloom_level?: int, type?: string}  $filters
     */
    public function paginateForTenant(
        string $tenantId,
        array $filters,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->with(['bank', 'currentVersion.psychometrics']);

        $this->applyFilters($query, $filters);

        return $query
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    public function create(string $tenantId, array $attributes): Question
    {
        $attributes['tenant_id'] = $tenantId;

        return $this->model->newQuery()->create($attributes);
    }

    public function update(Question $question, array $attributes): Question
    {
        $question->fill($attributes);
        $question->save();

        return $question;
    }

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
