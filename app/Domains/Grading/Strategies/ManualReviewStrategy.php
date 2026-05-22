<?php

declare(strict_types=1);

namespace App\Domains\Grading\Strategies;

use App\Domains\Grading\Contracts\GradingStrategy;
use App\Domains\Grading\DTOs\GradingRequest;
use App\Domains\Grading\DTOs\GradingResult;

class ManualReviewStrategy implements GradingStrategy
{
    private const SUPPORTED_TYPES = [
        'short_answer',
        'essay',
        'text',
        'long_text',
        'file_upload',
        'code',
        'oral',
        'practical',
    ];

    public function supports(string $questionType): bool
    {
        return in_array($questionType, self::SUPPORTED_TYPES, true);
    }

    public function grade(GradingRequest $request): GradingResult
    {
        return new GradingResult(
            sessionId: $request->sessionId,
            sessionItemId: $request->sessionItemId,
            questionId: $request->questionId,
            questionVersionId: $request->questionVersionId,
            isCorrect: null,
            rawScore: 0.0,
            maxScore: $request->maxScore,
            normalizedScore: 0.0,
            evaluationType: GradingResult::EVAL_TYPE_MANUAL_PENDING,
            evaluationStatus: GradingResult::STATUS_PENDING_REVIEW,
            requiresSecondaryReview: true,
            evaluationMetadata: [
                'strategy' => self::class,
                'question_type' => $request->questionType,
                'reason' => 'requires_human_evaluation',
            ],
        );
    }
}
