<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Exceptions;

use RuntimeException;

class CategoryNotEmptyException extends RuntimeException
{
    public function __construct(
        public readonly bool $hasChildren,
        public readonly bool $hasQuestions,
    ) {
        $reasons = [];

        if ($hasChildren) {
            $reasons[] = 'subcategories';
        }

        if ($hasQuestions) {
            $reasons[] = 'questions';
        }

        parent::__construct(
            'Cannot delete category: it still contains ' . implode(' and ', $reasons) . '.',
        );
    }
}
