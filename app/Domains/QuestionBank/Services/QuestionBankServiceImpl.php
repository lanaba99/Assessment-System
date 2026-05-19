<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Services;

use App\Domains\QuestionBank\Contracts\QuestionBankService;
use App\Domains\QuestionBank\DTOs\AdaptiveContext;
use App\Domains\QuestionBank\DTOs\BlueprintSpecification;
use App\Domains\QuestionBank\DTOs\CoverageReport;
use App\Domains\QuestionBank\DTOs\ItemPsychometrics;
use App\Domains\QuestionBank\DTOs\ItemResolutionRequest;
use App\Domains\QuestionBank\DTOs\ItemResolutionResult;
use App\Domains\QuestionBank\Enums\CalibrationStatus;
use App\Domains\QuestionBank\Models\QuestionVersion;
use App\Domains\QuestionBank\Repositories\QuestionBankRepository;
use App\Domains\QuestionBank\Strategies\ItemResolutionStrategyResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use RuntimeException;

class QuestionBankServiceImpl implements QuestionBankService
{
    private const MINIMUM_CALIBRATION_SAMPLE_SIZE = 30;

    private const MARGINAL_DISCRIMINATION_FLOOR = 0.20;

    private const STRONG_DISCRIMINATION_FLOOR = 0.30;

    private const ACCEPTABLE_DIFFICULTY_RANGE = [0.20, 0.90];

    public function __construct(
        private readonly QuestionBankRepository $repository,
        private readonly ItemResolutionStrategyResolver $strategies,
    ) {
    }

    public function resolveItems(ItemResolutionRequest $request): ItemResolutionResult
    {
        $this->guardRequest($request);

        $exposureExclusions = $this->repository->findVersionIdsAdministeredSince(
            $request->candidateId,
            CarbonImmutable::now()->subDays($request->exposureCooldownDays)->toIso8601String(),
        );

        $excluded = array_values(array_unique(array_merge(
            $request->excludedQuestionVersionIds,
            $exposureExclusions,
        )));

        $pool = $this->repository->findEligibleVersionsForCompetencies(
            $request->tenantId,
            array_keys($request->competencyWeights),
            $excluded,
            $request->requireCalibrated,
        );

        $pool = $pool->filter(
            fn ($version): bool => $this->meetsDiscriminationFloor($version, $request->minDiscrimination),
        )->values();

        $strategy = $this->strategies->resolve($request->strategy);
        $selected = $strategy->resolve($request, $pool);

        return $this->buildResult($selected, $request, $strategy->name());
    }

    public function resolveNextAdaptiveItem(AdaptiveContext $context): ?QuestionVersion
    {
        if (count($context->administeredVersionIds) >= $context->maxItems) {
            return null;
        }

        if ($context->standardError <= $context->stoppingStandardError) {
            return null;
        }

        $pool = $this->repository->findEligibleVersionsForCompetencies(
            $context->tenantId,
            array_keys($context->targetCompetencyWeights),
            $context->administeredVersionIds,
            true,
        );

        $candidate = $this->strategies->adaptive()->selectNextItem($context, $pool);

        return $candidate instanceof QuestionVersion ? $candidate : null;
    }

    public function recalibrateItem(string $questionVersionId): ItemPsychometrics
    {
        $version = $this->repository->findVersionWithPsychometrics($questionVersionId);

        if ($version === null) {
            throw new RuntimeException("Question version {$questionVersionId} not found.");
        }

        $responses = $this->repository->fetchResponsesForCalibration($questionVersionId);
        $sampleSize = $responses->count();
        $correctCount = $responses->where('is_correct', true)->count();

        if ($sampleSize === 0) {
            return $this->persistPsychometrics($version, [
                'tenant_id' => $version->question?->tenant_id,
                'difficulty_index' => null,
                'discrimination_index' => null,
                'point_biserial' => null,
                'sample_size' => 0,
                'correct_count' => 0,
                'is_calibrated' => false,
                'calibration_status' => CalibrationStatus::Pending->value,
                'calibration_metadata' => ['reason' => 'no_responses'],
                'last_calibrated_at' => CarbonImmutable::now(),
            ]);
        }

        $sessionIds = $responses->pluck('session_id')->unique()->values()->all();
        $sessionTotals = $this->repository->fetchSessionTotalScores($sessionIds);

        $difficultyIndex = $correctCount / $sampleSize;
        $discriminationIndex = $this->computeDiscriminationIndex($responses, $sessionTotals);
        $pointBiserial = $this->computePointBiserial($responses, $sessionTotals);

        $status = $this->classifyCalibration($sampleSize, $difficultyIndex, $discriminationIndex);

        return $this->persistPsychometrics($version, [
            'tenant_id' => $version->question?->tenant_id,
            'difficulty_index' => $difficultyIndex,
            'discrimination_index' => $discriminationIndex,
            'point_biserial' => $pointBiserial,
            'sample_size' => $sampleSize,
            'correct_count' => $correctCount,
            'is_calibrated' => $sampleSize >= self::MINIMUM_CALIBRATION_SAMPLE_SIZE,
            'calibration_status' => $status->value,
            'calibration_metadata' => [
                'method' => 'ctt',
                'upper_lower_group_share' => 0.27,
                'sessions_analyzed' => count($sessionIds),
            ],
            'last_calibrated_at' => CarbonImmutable::now(),
        ]);
    }

    public function recalibrateBatch(array $questionVersionIds): Collection
    {
        return collect($questionVersionIds)->map(
            fn (string $id): ItemPsychometrics => $this->recalibrateItem($id),
        );
    }

    public function flagItemsForReview(string $tenantId, float $discriminationThreshold = 0.20): Collection
    {
        return $this->repository
            ->findPsychometricsBelowDiscrimination($tenantId, $discriminationThreshold)
            ->map(fn ($record): array => [
                'question_version_id' => $record->question_version_id,
                'difficulty_index' => (float) $record->difficulty_index,
                'discrimination_index' => (float) $record->discrimination_index,
                'sample_size' => $record->sample_size,
                'recommendation' => $this->reviewRecommendation($record),
            ]);
    }

    public function analyzeCoverage(BlueprintSpecification $blueprint): CoverageReport
    {
        $competencyIds = array_keys($blueprint->competencyWeights);
        $bloomLevels = array_keys($blueprint->bloomDistribution);

        $competencyCounts = $this->repository->countCalibratedByCompetency($blueprint->tenantId, $competencyIds);
        $bloomCounts = $this->repository->countCalibratedByBloomLevel($blueprint->tenantId, $bloomLevels);

        $gaps = [];
        $competencyCoverage = [];

        foreach ($blueprint->competencyWeights as $competencyId => $weightPct) {
            $required = (int) round($blueprint->totalQuestions * ($weightPct / 100));
            $available = (int) ($competencyCounts[$competencyId] ?? 0);

            $competencyCoverage[$competencyId] = ['required' => $required, 'available' => $available];

            if ($available < $required) {
                $gaps[] = [
                    'axis' => 'competency',
                    'key' => $competencyId,
                    'required' => $required,
                    'available' => $available,
                ];
            }
        }

        $bloomCoverage = [];
        $bloomTotal = array_sum($blueprint->bloomDistribution);
        foreach ($blueprint->bloomDistribution as $bloomLevel => $share) {
            $required = $bloomTotal > 0
                ? (int) round($blueprint->totalQuestions * ($share / $bloomTotal))
                : 0;
            $available = (int) ($bloomCounts[$bloomLevel] ?? 0);

            $bloomCoverage[$bloomLevel] = ['required' => $required, 'available' => $available];

            if ($available < $required) {
                $gaps[] = [
                    'axis' => 'bloom',
                    'key' => $bloomLevel,
                    'required' => $required,
                    'available' => $available,
                ];
            }
        }

        return new CoverageReport(
            competencyCoverage: $competencyCoverage,
            bloomCoverage: $bloomCoverage,
            gaps: $gaps,
            isFeasible: $gaps === [],
        );
    }

    private function guardRequest(ItemResolutionRequest $request): void
    {
        if ($request->itemCount <= 0) {
            throw new RuntimeException('Item count must be positive.');
        }

        if ($request->competencyWeights === []) {
            throw new RuntimeException('At least one competency weight is required.');
        }

        $sum = array_sum($request->competencyWeights);
        if (abs($sum - 100.0) > 0.5) {
            throw new RuntimeException("Competency weights must sum to 100; got {$sum}.");
        }
    }

    private function meetsDiscriminationFloor(object $version, float $floor): bool
    {
        $d = $version->psychometrics?->discrimination_index;

        if ($d === null) {
            return false;
        }

        return (float) $d >= $floor;
    }

    private function computeDiscriminationIndex(Collection $responses, array $sessionTotals): ?float
    {
        if ($responses->count() < 4) {
            return null;
        }

        $ranked = $responses
            ->map(static function ($response) use ($sessionTotals): array {
                $total = $sessionTotals[$response->session_id]['total_score'] ?? 0.0;

                return [
                    'is_correct' => (bool) $response->is_correct,
                    'total_score' => (float) $total,
                ];
            })
            ->sortByDesc('total_score')
            ->values();

        $groupSize = max(1, (int) round($ranked->count() * 0.27));
        $upper = $ranked->take($groupSize);
        $lower = $ranked->reverse()->take($groupSize);

        $upperRate = $upper->where('is_correct', true)->count() / $groupSize;
        $lowerRate = $lower->where('is_correct', true)->count() / $groupSize;

        return $upperRate - $lowerRate;
    }

    private function computePointBiserial(Collection $responses, array $sessionTotals): ?float
    {
        $points = $responses->map(static function ($response) use ($sessionTotals): array {
            return [
                'x' => (bool) $response->is_correct ? 1 : 0,
                'y' => (float) ($sessionTotals[$response->session_id]['total_score'] ?? 0.0),
            ];
        });

        $n = $points->count();
        if ($n < 4) {
            return null;
        }

        $correct = $points->where('x', 1);
        $wrong = $points->where('x', 0);

        if ($correct->isEmpty() || $wrong->isEmpty()) {
            return 0.0;
        }

        $meanCorrect = $correct->avg('y');
        $meanWrong = $wrong->avg('y');

        $allY = $points->pluck('y');
        $meanAll = $allY->avg();
        $variance = $allY->reduce(
            static fn (float $carry, float $y): float => $carry + (($y - $meanAll) ** 2),
            0.0,
        ) / $n;
        $stdDev = sqrt($variance);

        if ($stdDev <= 0.0) {
            return 0.0;
        }

        $p = $correct->count() / $n;
        $q = 1.0 - $p;

        return (($meanCorrect - $meanWrong) / $stdDev) * sqrt($p * $q);
    }

    private function classifyCalibration(int $sampleSize, float $difficulty, ?float $discrimination): CalibrationStatus
    {
        if ($sampleSize < self::MINIMUM_CALIBRATION_SAMPLE_SIZE) {
            return CalibrationStatus::Pending;
        }

        [$minDiff, $maxDiff] = self::ACCEPTABLE_DIFFICULTY_RANGE;
        if ($difficulty < $minDiff || $difficulty > $maxDiff) {
            return CalibrationStatus::FlaggedForReview;
        }

        if ($discrimination === null || $discrimination < self::MARGINAL_DISCRIMINATION_FLOOR) {
            return CalibrationStatus::FlaggedForReview;
        }

        if ($discrimination < self::STRONG_DISCRIMINATION_FLOOR) {
            return CalibrationStatus::Marginal;
        }

        return CalibrationStatus::Calibrated;
    }

    private function reviewRecommendation(object $record): string
    {
        $d = (float) $record->discrimination_index;
        $p = (float) $record->difficulty_index;

        if ($d < 0.0) {
            return 'retire_negative_discrimination';
        }

        if ($p < 0.20 || $p > 0.90) {
            return 'review_difficulty_outlier';
        }

        return 'review_low_discrimination';
    }

    private function persistPsychometrics(QuestionVersion $version, array $attributes): ItemPsychometrics
    {
        $attributes['question_version_id'] = $version->version_id;
        $saved = $this->repository->upsertPsychometrics($attributes);

        return new ItemPsychometrics(
            questionVersionId: $saved->question_version_id,
            difficultyIndex: $saved->difficulty_index === null ? null : (float) $saved->difficulty_index,
            discriminationIndex: $saved->discrimination_index === null ? null : (float) $saved->discrimination_index,
            pointBiserial: $saved->point_biserial === null ? null : (float) $saved->point_biserial,
            sampleSize: $saved->sample_size,
            correctCount: $saved->correct_count,
            isCalibrated: $saved->is_calibrated,
            calibrationStatus: $saved->calibration_status,
            lastCalibratedAt: $saved->last_calibrated_at?->toImmutable(),
        );
    }

    private function buildResult(Collection $selected, ItemResolutionRequest $request, string $strategyName): ItemResolutionResult
    {
        $competencyTally = [];
        $bloomTally = [];
        $difficulties = [];
        $discriminations = [];

        foreach ($selected as $version) {
            $bloomLevel = (int) ($version->question->cognitive_level ?? 0);
            $bloomTally[$bloomLevel] = ($bloomTally[$bloomLevel] ?? 0) + 1;

            foreach (($version->question->competencies ?? []) as $competency) {
                $weight = (float) ($competency->pivot->weight_percentage ?? 0);
                $competencyTally[$competency->competency_id] = ($competencyTally[$competency->competency_id] ?? 0) + $weight;
            }

            if ($version->psychometrics) {
                $difficulties[] = (float) $version->psychometrics->difficulty_index;
                $discriminations[] = (float) $version->psychometrics->discrimination_index;
            }
        }

        $gaps = [];
        foreach ($request->competencyWeights as $competencyId => $expectedShare) {
            $observed = ($competencyTally[$competencyId] ?? 0) / max(1, $selected->count());
            if ($observed + 0.5 < $expectedShare) {
                $gaps[] = ['axis' => 'competency', 'key' => $competencyId, 'expected' => $expectedShare, 'observed' => $observed];
            }
        }

        return new ItemResolutionResult(
            items: $selected,
            achievedCompetencyMix: $competencyTally,
            achievedBloomMix: $bloomTally,
            achievedAverageDifficulty: $difficulties === [] ? 0.0 : array_sum($difficulties) / count($difficulties),
            achievedAverageDiscrimination: $discriminations === [] ? 0.0 : array_sum($discriminations) / count($discriminations),
            coverageGaps: $gaps,
            strategyUsed: $strategyName,
        );
    }
}
