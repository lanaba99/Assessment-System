<?php

declare(strict_types=1);

namespace App\Domains\Competency\Exceptions;

use RuntimeException;

class CompetencyNotEmptyException extends RuntimeException
{
    public function __construct(
        public readonly bool $hasChildren,
        public readonly bool $hasQuestions,
    ) {
        $reasons = [];

        if ($hasChildren) {
            $reasons[] = 'sub-competencies';
        }

        if ($hasQuestions) {
            $reasons[] = 'linked questions';
        }

        parent::__construct(
            'Cannot delete competency: it still contains ' . implode(' and ', $reasons) . '.',
        );
    }
}
