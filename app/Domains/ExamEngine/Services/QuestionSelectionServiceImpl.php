<?php

declare(strict_types=1);

namespace App\Domains\ExamEngine\Services;

use App\Domains\ExamEngine\Contracts\QuestionSelectionService;
use App\Domains\ExamEngine\DTOs\ResolvedSessionItem;
use App\Domains\ExamEngine\Exceptions\BlueprintNotFeasibleException;
use App\Domains\ExamEngine\Models\Exam;
use App\Domains\ExamEngine\Models\ExamSection;
use App\Domains\QuestionBank\Contracts\QuestionBankService;
use Illuminate\Support\Collection;

class QuestionSelectionServiceImpl implements QuestionSelectionService
{
    public function __construct(
        private readonly QuestionBankService $questionBank,
    ) {
    }

    /**
     * @throws BlueprintNotFeasibleException
     */
    public function assertBlueprintFeasible(Exam $exam): void
    {
        $sections = $this->sectionsWithBlueprints($exam);

        // An exam without any blueprints is not blueprint-constrained — it may
        // use manual question assignment or a different selection mechanism.
        // Only exams that HAVE blueprints are subject to the feasibility gate.
        if ($sections->isEmpty()) {
            return;
        }

        $tenantId = (string) $exam->tenant_id;
        $examId = (string) $exam->exam_id;
        $failingReports = [];

        foreach ($sections as $section) {
            $blueprints = $section->blueprints;

            if ($blueprints->isEmpty()) {
                continue;
            }

            $spec = BlueprintAssembler::toSpec($section, $blueprints, $tenantId, $examId);
            $report = $this->questionBank->analyzeCoverage($spec);

            if (! $report->isFeasible) {
                $failingReports[(string) $section->section_id] = $report;
            }
        }

        if ($failingReports !== []) {
            throw BlueprintNotFeasibleException::forSections($examId, $failingReports);
        }
    }

    /**
     * @param  array<int, string>  $excludedVersionIds
     * @return Collection<int, ResolvedSessionItem>
     */
    public function resolveQuestionsForSession(
        Exam $exam,
        string $candidateId,
        array $excludedVersionIds = [],
    ): Collection {
        $sections = $this->sectionsWithBlueprints($exam);
        $tenantId = (string) $exam->tenant_id;
        $resolved = new Collection();

        foreach ($sections as $section) {
            $blueprints = $section->blueprints;

            if ($blueprints->isEmpty()) {
                continue;
            }

            $request = BlueprintAssembler::toRequest(
                $section,
                $blueprints,
                $tenantId,
                $candidateId,
                $excludedVersionIds,
            );

            $result = $this->questionBank->resolveItems($request);

            foreach ($result->items as $questionVersion) {
                $resolved->push(new ResolvedSessionItem(
                    sectionId: (string) $section->section_id,
                    questionVersionId: (string) $questionVersion->version_id,
                ));

                // Prevent cross-section exposure of the same version.
                $excludedVersionIds[] = (string) $questionVersion->version_id;
            }
        }

        return $resolved;
    }

    /**
     * Loads sections ordered by sequence, with blueprints eager-loaded.
     * Sections without blueprints are excluded.
     *
     * @return Collection<int, ExamSection>
     */
    private function sectionsWithBlueprints(Exam $exam): Collection
    {
        return $exam->sections()
            ->with('blueprints')
            ->orderBy('section_sequence')
            ->get()
            ->filter(static fn (ExamSection $s): bool => $s->blueprints->isNotEmpty())
            ->values();
    }
}
