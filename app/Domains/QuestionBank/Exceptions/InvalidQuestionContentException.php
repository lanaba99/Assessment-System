<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Exceptions;

use RuntimeException;

/**
 * Raised when type-specific content fails a strategy's validation (e.g. a
 * true/false question with no answer, an MCQ with no correct option).
 *
 * Extends RuntimeException so the controllers' existing catch maps it to a
 * 422 Unprocessable Entity without special handling.
 */
class InvalidQuestionContentException extends RuntimeException
{
}
