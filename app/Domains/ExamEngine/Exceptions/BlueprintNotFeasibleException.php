<?php

declare(strict_types=1);

namespace App\Domains\ExamEngine\Exceptions;

use App\Domains\QuestionBank\DTOs\CoverageReport;
use RuntimeException;

class BlueprintNotFeasibleException extends RuntimeException
{
    /**
     * @param  array<string, CoverageReport>  $failingReports  section_id → CoverageReport
     */
    public function __construct(
        public readonly array $failingReports,
        string $examId,
    ) {
        $sectionCount = count($failingReports);
        $gapSummary = implode(', ', array_map(
            static fn (CoverageReport $r): string => implode('|', $r->gaps),
            $failingReports,
        ));

        parent::__construct(
            "Exam [{$examId}] cannot be published: {$sectionCount} section(s) have insufficient "
            . "question coverage. Gaps: {$gapSummary}",
        );
    }

    public static function forSections(string $examId, array $failingReports): self
    {
        return new self($failingReports, $examId);
    }
}
