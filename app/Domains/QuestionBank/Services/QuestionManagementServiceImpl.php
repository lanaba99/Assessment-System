<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Services;

use App\Domains\QuestionBank\Contracts\QuestionManagementService;
use App\Domains\QuestionBank\Models\Question;
use App\Domains\QuestionBank\Repositories\CategoryRepository;
use App\Domains\QuestionBank\Repositories\QuestionRepository;
use App\Domains\QuestionBank\Repositories\QuestionVersionRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class QuestionManagementServiceImpl implements QuestionManagementService
{
    public function __construct(
        private readonly QuestionRepository $questions,
        private readonly CategoryRepository $categories,
        private readonly QuestionVersionRepository $versions,
    ) {
    }

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
    ): Question {
        if ($this->categories->findById($tenantId, $categoryId) === null) {
            throw new RuntimeException('Category not found.');
        }

        if ($type === 'mcq' && count($choices) < 2) {
            throw new RuntimeException('MCQ questions require at least two choices.');
        }

        return DB::transaction(function () use (
            $tenantId,
            $categoryId,
            $createdByUserId,
            $title,
            $type,
            $questionText,
            $stem,
            $bloomLevel,
            $difficultyLevel,
            $choices,
            $psychometrics,
        ): Question {
            $question = $this->questions->create($tenantId, [
                'category_id' => $categoryId,
                'created_by_user_id' => $createdByUserId,
                'question_title' => $title,
                'question_type' => $type,
                'difficulty_level' => $difficultyLevel,
                'cognitive_level' => $bloomLevel,
                'total_usage_count' => (int) ($psychometrics['usage_count'] ?? 0),
            ]);

            $version = $this->versions->create([
                'question_id' => $question->question_id,
                'created_by_user_id' => $createdByUserId,
                'ver_num' => 1,
                'question_text' => $questionText,
                'question_type' => $type,
                'question_stem' => $stem,
                'approval_status' => 'draft',
                'created_at' => now(),
            ]);

            if ($type === 'mcq') {
                $this->versions->replaceOptions($version, $choices);
            }

            $this->versions->createPsychometrics(
                $tenantId,
                (string) $version->version_id,
                $this->mapPsychometricsAttributes($psychometrics),
            );

            $this->questions->update($question, [
                'current_version_id' => $version->version_id,
            ]);

            return $this->questions->findByIdWithDetails($tenantId, (string) $question->question_id)
                ?? $question->refresh();
        });
    }

    public function listQuestions(string $tenantId, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->questions->paginateForTenant($tenantId, $filters, $perPage);
    }

    public function getQuestion(string $tenantId, string $questionId): Question
    {
        $question = $this->questions->findByIdWithDetails($tenantId, $questionId);

        if ($question === null) {
            throw new RuntimeException('Question not found.');
        }

        return $question;
    }

    public function updateQuestion(
        string $tenantId,
        string $questionId,
        array $questionAttributes,
        ?array $versionAttributes = null,
        ?array $choices = null,
        ?array $psychometrics = null,
    ): Question {
        $question = $this->questions->findByIdWithDetails($tenantId, $questionId);

        if ($question === null) {
            throw new RuntimeException('Question not found.');
        }

        return DB::transaction(function () use (
            $tenantId,
            $question,
            $questionAttributes,
            $versionAttributes,
            $choices,
            $psychometrics,
        ): Question {
            if ($questionAttributes !== []) {
                $this->questions->update($question, $questionAttributes);
            }

            $version = $question->currentVersion;

            if ($version !== null) {
                if ($versionAttributes !== null && $versionAttributes !== []) {
                    $this->versions->update($version, $versionAttributes);
                }

                if ($choices !== null && (string) $question->question_type === 'mcq') {
                    $this->versions->replaceOptions($version, $choices);
                }

                if ($psychometrics !== null) {
                    $existing = $this->versions->findPsychometricsByVersionId(
                        $tenantId,
                        (string) $version->version_id,
                    );

                    $mapped = $this->mapPsychometricsAttributes($psychometrics);

                    if ($existing !== null) {
                        $this->versions->updatePsychometrics($existing, $mapped);
                    } else {
                        $this->versions->createPsychometrics(
                            $tenantId,
                            (string) $version->version_id,
                            $mapped,
                        );
                    }
                }
            }

            return $this->questions->findByIdWithDetails($tenantId, (string) $question->question_id)
                ?? $question->refresh();
        });
    }

    public function deleteQuestion(string $tenantId, string $questionId): void
    {
        $question = $this->questions->findById($tenantId, $questionId);

        if ($question === null) {
            throw new RuntimeException('Question not found.');
        }

        $this->questions->delete($question);
    }

    /**
     * @param  array<string, mixed>  $psychometrics
     * @return array<string, mixed>
     */
    private function mapPsychometricsAttributes(array $psychometrics): array
    {
        $mapped = [];

        if (array_key_exists('p_value', $psychometrics)) {
            $mapped['difficulty_index'] = $psychometrics['p_value'];
        }

        if (array_key_exists('discrimination_index', $psychometrics)) {
            $mapped['discrimination_index'] = $psychometrics['discrimination_index'];
        }

        if (array_key_exists('usage_count', $psychometrics)) {
            // usage_count is stored on the question header; handled separately in update paths.
        }

        return $mapped;
    }
}
