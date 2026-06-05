<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\QuestionTypes;

use App\Domains\QuestionBank\DTOs\QuestionContentDraft;
use App\Domains\QuestionBank\DTOs\ResolvedQuestionContent;
use App\Domains\QuestionBank\Enums\QuestionType;

/**
 * Encapsulates everything type-specific about authoring a question: what valid
 * content looks like, and how to turn that content into persistable options +
 * an answer key. The service is type-agnostic and delegates entirely here.
 */
interface QuestionTypeStrategy
{
    public function type(): QuestionType;

    /**
     * @throws \App\Domains\QuestionBank\Exceptions\InvalidQuestionContentException
     */
    public function validate(QuestionContentDraft $draft): void;

    public function resolve(QuestionContentDraft $draft): ResolvedQuestionContent;
}
