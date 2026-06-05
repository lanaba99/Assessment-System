<?php

declare(strict_types=1);

namespace App\Domains\ExamEngine\Exceptions;

use RuntimeException;

class ExamNotFoundException extends RuntimeException
{
    public static function forId(string $examId): self
    {
        return new self("Exam [{$examId}] not found.");
    }
}
