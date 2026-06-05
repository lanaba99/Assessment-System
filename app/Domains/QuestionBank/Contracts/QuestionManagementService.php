<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Contracts;

use App\Domains\QuestionBank\Models\Question;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Tenant scoping is implicit (BelongsToTenant global scope) — no method takes
 * a tenant id. Type-specific content is carried in $answer / $evaluatorInstructions
 * and interpreted by a QuestionTypeStrategy.
 */
interface QuestionManagementService
{
    /**
     * @param  array<int, array{option_text: string, is_correct: bool, option_sequence?: int}>  $choices
     * @param  array<string, mixed>  $answer            Type-specific answer payload (true/false, short-answer, …).
     * @param  array<string, mixed>|null  $evaluatorInstructions
     * @param  array<string, mixed>  $psychometrics
     */
    public function createQuestion(
        string $categoryId,
        string $createdByUserId,
        string $title,
        string $type,
        string $questionText,
        ?string $stem,
        int $bloomLevel,
        int $difficultyLevel,
        array $choices,
        array $answer = [],
        ?array $evaluatorInstructions = null,
        array $psychometrics = [],
    ): Question;

    /**
     * @param  array{category_id?: string, bloom_level?: int, type?: string}  $filters
     */
    public function listQuestions(array $filters, int $perPage = 15): LengthAwarePaginator;

    public function getQuestion(string $questionId): Question;

    /**
     * A content change (version/choice/answer attributes) spawns a new
     * immutable version authored by $editedByUserId; header-only changes edit
     * in place.
     *
     * @param  array<string, mixed>  $questionAttributes
     * @param  array<string, mixed>|null  $versionAttributes
     * @param  array<int, array{option_text: string, is_correct: bool, option_sequence?: int}>|null  $choices
     * @param  array<string, mixed>|null  $answer
     * @param  array<string, mixed>|null  $evaluatorInstructions
     * @param  array<string, mixed>|null  $psychometrics
     */
    public function updateQuestion(
        string $questionId,
        string $editedByUserId,
        array $questionAttributes,
        ?array $versionAttributes = null,
        ?array $choices = null,
        ?array $answer = null,
        ?array $evaluatorInstructions = null,
        ?array $psychometrics = null,
    ): Question;

    public function deleteQuestion(string $questionId): void;
}
