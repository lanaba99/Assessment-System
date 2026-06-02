<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Contracts;

use App\Domains\QuestionBank\Models\Question;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface QuestionManagementService
{
    /**
     * @param  array<int, array{option_text: string, is_correct: bool, option_sequence?: int}>  $choices
     * @param  array<string, mixed>  $psychometrics
     */
    public function createQuestion(
        string $tenantId,
        string $categoryId,
        string $createdByUserId,
        string $title,
        string $type,
        string $questionText,
        ?string $stem,
        int $bloomLevel,
        int $difficultyLevel,
        array $choices,
        array $psychometrics = [],
    ): Question;

    /**
     * @param  array{category_id?: string, bloom_level?: int, type?: string}  $filters
     */
    public function listQuestions(string $tenantId, array $filters, int $perPage = 15): LengthAwarePaginator;

    public function getQuestion(string $tenantId, string $questionId): Question;

    /**
     * @param  array<string, mixed>  $questionAttributes
     * @param  array<string, mixed>|null  $versionAttributes
     * @param  array<int, array{option_text: string, is_correct: bool, option_sequence?: int}>|null  $choices
     * @param  array<string, mixed>|null  $psychometrics
     */
    public function updateQuestion(
        string $tenantId,
        string $questionId,
        array $questionAttributes,
        ?array $versionAttributes = null,
        ?array $choices = null,
        ?array $psychometrics = null,
    ): Question;

    public function deleteQuestion(string $tenantId, string $questionId): void;
}
