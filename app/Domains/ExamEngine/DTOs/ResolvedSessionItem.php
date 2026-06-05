<?php

declare(strict_types=1);

namespace App\Domains\ExamEngine\DTOs;

/**
 * One resolved question in a candidate's session plan, ordered by sequence.
 * Produced by QuestionSelectionService and consumed by ExamSessionService to
 * write exam_session_items rows.
 */
final readonly class ResolvedSessionItem
{
    public function __construct(
        public string $sectionId,
        public string $questionVersionId,
    ) {
    }
}
