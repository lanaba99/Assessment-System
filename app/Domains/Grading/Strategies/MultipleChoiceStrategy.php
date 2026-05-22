<?php

declare(strict_types=1);

namespace App\Domains\Grading\Strategies;

use App\Domains\Grading\Contracts\GradingStrategy;
use App\Domains\Grading\DTOs\GradingRequest;
use App\Domains\Grading\DTOs\GradingResult;

class MultipleChoiceStrategy implements GradingStrategy
{
    private const SUPPORTED_TYPES = [
        'multiple_choice',
        'multi_select',
        'single_choice',
        'true_false',
    ];

    public function supports(string $questionType): bool
    {
        return in_array($questionType, self::SUPPORTED_TYPES, true);
    }

    public function grade(GradingRequest $request): GradingResult
    {
        $expected = $this->normalize($this->extractKey($request->correctAnswerKey));
        $actual = $this->normalize($request->selectedOptions ?? []);

        $hasKey = $expected !== [];
        $isCorrect = $hasKey && $expected === $actual;

        $rawScore = $isCorrect ? $request->maxScore : 0.0;
        $normalized = $request->maxScore > 0.0 ? $rawScore / $request->maxScore : 0.0;

        return new GradingResult(
            sessionId: $request->sessionId,
            sessionItemId: $request->sessionItemId,
            questionId: $request->questionId,
            questionVersionId: $request->questionVersionId,
            isCorrect: $hasKey ? $isCorrect : null,
            rawScore: $rawScore,
            maxScore: $request->maxScore,
            normalizedScore: $normalized,
            evaluationType: GradingResult::EVAL_TYPE_AUTO,
            evaluationStatus: $hasKey ? GradingResult::STATUS_SCORED : GradingResult::STATUS_PENDING_REVIEW,
            requiresSecondaryReview: ! $hasKey,
            evaluationMetadata: [
                'strategy' => self::class,
                'question_type' => $request->questionType,
                'expected' => $expected,
                'actual' => $actual,
                'answer_key_missing' => ! $hasKey,
            ],
        );
    }

    /**
     * Tolerates both shapes for `correct_answer_json`:
     *   - flat list of option ids: ["opt-uuid-a", "opt-uuid-b"]
     *   - object with `options` key:  {"options": ["opt-uuid-a"]}
     *
     * @return array<int, string>
     */
    private function extractKey(?array $correctAnswerKey): array
    {
        if ($correctAnswerKey === null) {
            return [];
        }

        if (isset($correctAnswerKey['options']) && is_array($correctAnswerKey['options'])) {
            return $correctAnswerKey['options'];
        }

        return array_values($correctAnswerKey);
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @return array<int, string>
     */
    private function normalize(array $values): array
    {
        $strings = array_values(array_map(static fn ($v): string => (string) $v, $values));
        sort($strings);

        return $strings;
    }
}
