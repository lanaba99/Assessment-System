<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\QuestionTypes;

use App\Domains\QuestionBank\DTOs\QuestionContentDraft;
use App\Domains\QuestionBank\DTOs\ResolvedQuestionContent;
use App\Domains\QuestionBank\Enums\QuestionType;
use App\Domains\QuestionBank\Exceptions\InvalidQuestionContentException;

class ShortAnswerStrategy implements QuestionTypeStrategy
{
    /**
     * @var array<int, string>
     */
    private const MATCH_MODES = ['exact', 'case_insensitive'];

    public function type(): QuestionType
    {
        return QuestionType::ShortAnswer;
    }

    public function validate(QuestionContentDraft $draft): void
    {
        $accepted = $draft->answer['accepted_answers'] ?? null;

        if (! is_array($accepted) || $accepted === []) {
            throw new InvalidQuestionContentException('Short-answer questions require at least one accepted answer.');
        }

        foreach ($accepted as $answer) {
            if (trim((string) $answer) === '') {
                throw new InvalidQuestionContentException('Accepted answers cannot be blank.');
            }
        }

        $matchMode = $draft->answer['match_mode'] ?? 'case_insensitive';

        if (! in_array($matchMode, self::MATCH_MODES, true)) {
            throw new InvalidQuestionContentException('Unsupported match_mode for short answer.');
        }
    }

    public function resolve(QuestionContentDraft $draft): ResolvedQuestionContent
    {
        $accepted = array_values(array_map(
            static fn ($answer): string => trim((string) $answer),
            $draft->answer['accepted_answers'],
        ));

        return new ResolvedQuestionContent(
            correctAnswer: [
                'accepted' => $accepted,
                'match' => $draft->answer['match_mode'] ?? 'case_insensitive',
            ],
            evaluatorInstructions: $draft->evaluatorInstructions,
        );
    }
}
