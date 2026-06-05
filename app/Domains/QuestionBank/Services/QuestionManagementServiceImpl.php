<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Services;

use App\Domains\QuestionBank\Contracts\QuestionManagementService;
use App\Domains\QuestionBank\DTOs\QuestionContentDraft;
use App\Domains\QuestionBank\DTOs\ResolvedQuestionContent;
use App\Domains\QuestionBank\Enums\QuestionType;
use App\Domains\QuestionBank\Models\Question;
use App\Domains\QuestionBank\Models\QuestionVersion;
use App\Domains\QuestionBank\QuestionTypes\QuestionTypeStrategyResolver;
use App\Domains\QuestionBank\Repositories\CategoryRepository;
use App\Domains\QuestionBank\Repositories\QuestionRepository;
use App\Domains\QuestionBank\Repositories\QuestionVersionRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Tenant binding is handled transparently by the BelongsToTenant global scope
 * on the models (audit Option A), so no method here accepts or threads a
 * tenant id.
 *
 * All type-specific content handling (MCQ / true-false / short-answer / essay)
 * is delegated to a QuestionTypeStrategy — this service is type-agnostic.
 */
class QuestionManagementServiceImpl implements QuestionManagementService
{
    public function __construct(
        private readonly QuestionRepository $questions,
        private readonly CategoryRepository $categories,
        private readonly QuestionVersionRepository $versions,
        private readonly QuestionTypeStrategyResolver $typeStrategies,
    ) {
    }

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
    ): Question {
        if (! $this->categories->exists($categoryId)) {
            throw new RuntimeException('Category not found.');
        }

        $questionType = QuestionType::from($type);
        $strategy = $this->typeStrategies->for($questionType);

        $draft = new QuestionContentDraft(
            type: $questionType,
            questionText: $questionText,
            stem: $stem,
            choices: $choices,
            answer: $answer,
            evaluatorInstructions: $evaluatorInstructions,
        );

        // Throws InvalidQuestionContentException (→ 422) on bad content.
        $strategy->validate($draft);
        $resolved = $strategy->resolve($draft);

        return DB::transaction(function () use (
            $categoryId,
            $createdByUserId,
            $title,
            $questionType,
            $questionText,
            $stem,
            $bloomLevel,
            $difficultyLevel,
            $resolved,
            $psychometrics,
        ): Question {
            $question = $this->questions->create([
                'category_id' => $categoryId,
                'created_by_user_id' => $createdByUserId,
                'question_title' => $title,
                'question_type' => $questionType->value,
                'difficulty_level' => $difficultyLevel,
                'cognitive_level' => $bloomLevel,
                'total_usage_count' => (int) ($psychometrics['usage_count'] ?? 0),
            ]);

            $version = $this->writeVersion(
                $question,
                $questionType,
                $createdByUserId,
                1,
                $questionText,
                $stem,
                $resolved,
            );

            $this->versions->createPsychometrics(
                (string) $version->version_id,
                $this->mapPsychometricsAttributes($psychometrics),
            );

            $this->questions->update($question, [
                'current_version_id' => $version->version_id,
            ]);

            return $this->questions->findByIdWithDetails((string) $question->question_id)
                ?? $question->refresh();
        });
    }

    public function listQuestions(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->questions->paginate($filters, $perPage);
    }

    public function getQuestion(string $questionId): Question
    {
        $question = $this->questions->findByIdWithDetails($questionId);

        if ($question === null) {
            throw new RuntimeException('Question not found.');
        }

        return $question;
    }

    /**
     * True versioning (audit Priority #1).
     *
     * Version-independent header fields (title, category, difficulty, usage)
     * edit the `questions` row in place. Any change to question CONTENT
     * (text/stem/options/answer/evaluator instructions) instead spawns a new
     * immutable `question_versions` row, bumps the version number, and
     * repoints `current_version_id` — leaving every prior version (and any
     * exam session pinned to it) intact.
     *
     * Psychometrics arriving WITHOUT a content change are a recalibration of
     * the current version and update its row in place; psychometrics arriving
     * WITH a content change seed the (uncalibrated) new version.
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
    ): Question {
        $question = $this->questions->findByIdWithDetails($questionId);

        if ($question === null) {
            throw new RuntimeException('Question not found.');
        }

        return DB::transaction(function () use (
            $question,
            $editedByUserId,
            $questionAttributes,
            $versionAttributes,
            $choices,
            $answer,
            $evaluatorInstructions,
            $psychometrics,
        ): Question {
            if ($questionAttributes !== []) {
                $this->questions->update($question, $questionAttributes);
            }

            $current = $question->currentVersion;
            $contentChanged = ($versionAttributes !== null && $versionAttributes !== [])
                || $choices !== null
                || ($answer !== null && $answer !== [])
                || $evaluatorInstructions !== null;

            if ($contentChanged) {
                $newVersion = $this->spawnVersion(
                    $question,
                    $current,
                    $editedByUserId,
                    $versionAttributes,
                    $choices,
                    $answer,
                    $evaluatorInstructions,
                );

                $this->versions->createPsychometrics(
                    (string) $newVersion->version_id,
                    $psychometrics !== null ? $this->mapPsychometricsAttributes($psychometrics) : [],
                );

                $this->questions->update($question, [
                    'current_version_id' => $newVersion->version_id,
                ]);
            } elseif ($psychometrics !== null && $current !== null) {
                $this->upsertPsychometrics((string) $current->version_id, $psychometrics);
            }

            return $this->questions->findByIdWithDetails((string) $question->question_id)
                ?? $question->refresh();
        });
    }

    public function deleteQuestion(string $questionId): void
    {
        $question = $this->questions->findById($questionId);

        if ($question === null) {
            throw new RuntimeException('Question not found.');
        }

        // Soft delete (SoftDeletes trait) — never destroys versions/responses.
        $this->questions->delete($question);
    }

    /**
     * Build the next immutable version, carrying forward any content the
     * caller did not explicitly change, then re-resolving it through the type
     * strategy so the answer key / options stay internally consistent.
     *
     * @param  array<string, mixed>|null  $versionAttributes
     * @param  array<int, array{option_text: string, is_correct: bool, option_sequence?: int}>|null  $choices
     * @param  array<string, mixed>|null  $answer
     * @param  array<string, mixed>|null  $evaluatorInstructions
     */
    private function spawnVersion(
        Question $question,
        ?QuestionVersion $current,
        string $editedByUserId,
        ?array $versionAttributes,
        ?array $choices,
        ?array $answer,
        ?array $evaluatorInstructions,
    ): QuestionVersion {
        $type = QuestionType::from((string) $question->question_type);
        $strategy = $this->typeStrategies->for($type);

        $questionText = $versionAttributes['question_text'] ?? $current?->question_text ?? '';
        $stem = array_key_exists('question_stem', $versionAttributes ?? [])
            ? $versionAttributes['question_stem']
            : $current?->question_stem;

        $draft = new QuestionContentDraft(
            type: $type,
            questionText: $questionText,
            stem: $stem,
            choices: $choices ?? $this->carryForwardOptions($current),
            answer: $answer ?? $this->carryForwardAnswer($type, $current),
            evaluatorInstructions: $evaluatorInstructions ?? $current?->evaluator_instructions,
        );

        $strategy->validate($draft);
        $resolved = $strategy->resolve($draft);

        return $this->writeVersion(
            $question,
            $type,
            $editedByUserId,
            $this->versions->nextVersionNumber((string) $question->question_id),
            $questionText,
            $stem,
            $resolved,
        );
    }

    /**
     * Persist one version row + its option set from already-resolved content.
     * The single place version columns are written, shared by create + update.
     */
    private function writeVersion(
        Question $question,
        QuestionType $type,
        string $authorUserId,
        int $versionNumber,
        string $questionText,
        ?string $stem,
        ResolvedQuestionContent $resolved,
    ): QuestionVersion {
        $version = $this->versions->create([
            'question_id' => $question->question_id,
            'created_by_user_id' => $authorUserId,
            'ver_num' => $versionNumber,
            'question_text' => $questionText,
            'question_type' => $type->value,
            'question_stem' => $stem,
            'correct_answer_json' => $resolved->correctAnswer,
            'evaluator_instructions' => $resolved->evaluatorInstructions,
            'approval_status' => 'draft',
            'content_hash' => $this->contentHash($type, $questionText, $stem, $resolved),
            'created_at' => now(),
        ]);

        if ($resolved->options !== []) {
            $this->versions->createOptions($version, $resolved->options);
        }

        return $version;
    }

    /**
     * Snapshot the current version's options as a plain choices payload so a
     * carried-forward MCQ version is self-contained.
     *
     * @return array<int, array{option_text: string, is_correct: bool, option_sequence: int, option_metadata: array<string, mixed>|null}>
     */
    private function carryForwardOptions(?QuestionVersion $current): array
    {
        if ($current === null) {
            return [];
        }

        return $current->options
            ->sortBy('option_sequence')
            ->map(static fn ($option): array => [
                'option_text' => (string) $option->option_text,
                'is_correct' => (bool) $option->is_correct,
                'option_sequence' => (int) $option->option_sequence,
                'option_metadata' => $option->option_metadata,
            ])
            ->values()
            ->all();
    }

    /**
     * Reconstruct the type-specific answer payload from the current version so
     * a content edit that doesn't restate the answer still re-resolves cleanly.
     *
     * @return array<string, mixed>
     */
    private function carryForwardAnswer(QuestionType $type, ?QuestionVersion $current): array
    {
        $stored = $current?->correct_answer_json;

        if (! is_array($stored)) {
            return [];
        }

        return match ($type) {
            QuestionType::TrueFalse => array_key_exists('value', $stored)
                ? ['correct_answer' => (bool) $stored['value']]
                : [],
            QuestionType::ShortAnswer => [
                'accepted_answers' => $stored['accepted'] ?? [],
                'match_mode' => $stored['match'] ?? 'case_insensitive',
            ],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $psychometrics
     */
    private function upsertPsychometrics(string $versionId, array $psychometrics): void
    {
        $mapped = $this->mapPsychometricsAttributes($psychometrics);
        $existing = $this->versions->findPsychometricsByVersionId($versionId);

        if ($existing !== null) {
            $this->versions->updatePsychometrics($existing, $mapped);

            return;
        }

        $this->versions->createPsychometrics($versionId, $mapped);
    }

    /**
     * A stable fingerprint of gradable content, used to detect no-op edits and
     * to tie a delivered exam item back to the exact content a candidate saw.
     */
    private function contentHash(QuestionType $type, string $text, ?string $stem, ResolvedQuestionContent $resolved): string
    {
        $normalized = [
            'type' => $type->value,
            'text' => $text,
            'stem' => $stem,
            'options' => array_map(static fn (array $option): array => [
                'text' => $option['option_text'] ?? null,
                'is_correct' => (bool) ($option['is_correct'] ?? false),
                'sequence' => $option['option_sequence'] ?? null,
            ], array_values($resolved->options)),
            'answer' => $resolved->correctAnswer,
        ];

        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
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

        // Note: usage_count lives on the question header (total_usage_count),
        // not on the psychometrics row, and is mapped by the form request.

        return $mapped;
    }
}
