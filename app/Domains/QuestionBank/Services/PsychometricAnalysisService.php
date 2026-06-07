<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Services;

use App\Domains\ExamSession\Repositories\ExamSessionItemRepository;
use App\Domains\Grading\Repositories\AnswerEvaluationRepository;
use App\Domains\QuestionBank\DTOs\ItemPsychometrics;
use App\Domains\QuestionBank\Repositories\QuestionPsychometricsRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PsychometricAnalysisService
{
    private const MIN_SAMPLES_FOR_STATS = 5;

    private const MIN_SAMPLES_FOR_CALIBRATION = 30;

    private const STATUS_AWAITING_SAMPLES = 'awaiting_samples';

    private const STATUS_PROVISIONAL = 'provisional';

    private const STATUS_CALIBRATED = 'calibrated';

    public function __construct(
        private readonly ExamSessionItemRepository $itemRepository,
        private readonly AnswerEvaluationRepository $evaluationRepository,
        private readonly QuestionPsychometricsRepository $psychometricsRepository,
    ) {
    }

    public function analyzeSession(string $sessionId): void
    {
        $items = $this->itemRepository->findBySession($sessionId);

        if ($items->isEmpty()) {
            return;
        }

        $tenantId = $this->resolveTenantId($items);
        $versionIds = $items
            ->pluck('question_version_id')
            ->filter()
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();

        foreach ($versionIds as $versionId) {
            $this->recalibrateVersion($tenantId, $versionId);
        }
    }

    private function recalibrateVersion(string $tenantId, string $versionId): void
    {
        DB::transaction(function () use ($tenantId, $versionId): void {
            $samples = $this->buildSamples($tenantId, $versionId);
            $metrics = $this->computeMetrics($versionId, $samples);
            $this->psychometricsRepository->upsert($tenantId, $metrics);
        });
    }

    /**
     * @return array<int, array{session_id: string, is_correct: bool, session_total: float}>
     */
    private function buildSamples(string $tenantId, string $versionId): array
    {
        $evaluations = $this->evaluationRepository->findByQuestionVersionId($tenantId, $versionId);

        if ($evaluations->isEmpty()) {
            return [];
        }

        $sessionIds = $evaluations
            ->pluck('session_id')
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();

        $sessionTotals = $this->evaluationRepository->getSessionTotalScores($tenantId, $sessionIds);

        $samples = [];
        foreach ($evaluations as $eval) {
            $metadata = is_array($eval->evaluation_metadata) ? $eval->evaluation_metadata : [];
            $isCorrect = $metadata['is_correct'] ?? null;

            if (! is_bool($isCorrect)) {
                continue;
            }

            $sid = (string) $eval->session_id;
            $samples[] = [
                'session_id' => $sid,
                'is_correct' => $isCorrect,
                'session_total' => (float) ($sessionTotals[$sid] ?? 0.0),
            ];
        }

        return $samples;
    }

    /**
     * @param  array<int, array{session_id: string, is_correct: bool, session_total: float}>  $samples
     */
    private function computeMetrics(string $versionId, array $samples): ItemPsychometrics
    {
        $n = count($samples);
        $correctCount = $this->countCorrect($samples);
        $now = CarbonImmutable::now();

        if ($n < self::MIN_SAMPLES_FOR_STATS) {
            return new ItemPsychometrics(
                questionVersionId: $versionId,
                difficultyIndex: $n > 0 ? $correctCount / $n : null,
                discriminationIndex: null,
                pointBiserial: null,
                sampleSize: $n,
                correctCount: $correctCount,
                isCalibrated: false,
                calibrationStatus: self::STATUS_AWAITING_SAMPLES,
                lastCalibratedAt: $now,
            );
        }

        $pValue = $correctCount / $n;
        $pointBiserial = $this->calculatePointBiserial($samples, $pValue, $correctCount);
        $classicalD = $this->calculateClassicalDiscrimination($samples);

        $isCalibrated = $n >= self::MIN_SAMPLES_FOR_CALIBRATION;

        return new ItemPsychometrics(
            questionVersionId: $versionId,
            difficultyIndex: round($pValue, 4),
            discriminationIndex: round($classicalD, 4),
            pointBiserial: round($pointBiserial, 4),
            sampleSize: $n,
            correctCount: $correctCount,
            isCalibrated: $isCalibrated,
            calibrationStatus: $isCalibrated ? self::STATUS_CALIBRATED : self::STATUS_PROVISIONAL,
            lastCalibratedAt: $now,
        );
    }

    /**
     * Point-biserial correlation between item correctness and total exam score.
     *
     *   r_pbis = ((M_correct − M_wrong) / σ_total) × √(p · q)
     *
     * Returns 0.0 when the formula is undefined (zero variance, or unanimous correct/wrong).
     *
     * @param  array<int, array{is_correct: bool, session_total: float}>  $samples
     */
    private function calculatePointBiserial(array $samples, float $p, int $correctCount): float
    {
        $n = count($samples);
        $wrongCount = $n - $correctCount;

        if ($correctCount === 0 || $wrongCount === 0) {
            return 0.0;
        }

        $totals = array_column($samples, 'session_total');
        $meanTotal = array_sum($totals) / $n;

        $variance = 0.0;
        foreach ($totals as $t) {
            $variance += ($t - $meanTotal) ** 2;
        }
        $stdDev = sqrt($variance / $n);

        if ($stdDev <= 0.0) {
            return 0.0;
        }

        $sumCorrect = 0.0;
        $sumWrong = 0.0;
        foreach ($samples as $s) {
            if ($s['is_correct']) {
                $sumCorrect += $s['session_total'];
            } else {
                $sumWrong += $s['session_total'];
            }
        }

        $meanCorrect = $sumCorrect / $correctCount;
        $meanWrong = $sumWrong / $wrongCount;
        $q = 1.0 - $p;

        return (($meanCorrect - $meanWrong) / $stdDev) * sqrt($p * $q);
    }

    /**
     * Classical Discrimination Index D = P_upper − P_lower,
     * where upper = top 27% of scorers and lower = bottom 27%.
     *
     * @param  array<int, array{is_correct: bool, session_total: float}>  $samples
     */
    private function calculateClassicalDiscrimination(array $samples): float
    {
        $n = count($samples);
        if ($n < 4) {
            return 0.0;
        }

        $sorted = $samples;
        usort($sorted, static fn ($a, $b): int => $a['session_total'] <=> $b['session_total']);

        $groupSize = max(1, (int) floor($n * 0.27));

        $lower = array_slice($sorted, 0, $groupSize);
        $upper = array_slice($sorted, $n - $groupSize, $groupSize);

        $pLower = $this->proportionCorrect($lower);
        $pUpper = $this->proportionCorrect($upper);

        return $pUpper - $pLower;
    }

    /**
     * @param  array<int, array{is_correct: bool, session_total: float}>  $samples
     */
    private function countCorrect(array $samples): int
    {
        $count = 0;
        foreach ($samples as $s) {
            if ($s['is_correct']) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  array<int, array{is_correct: bool, session_total: float}>  $samples
     */
    private function proportionCorrect(array $samples): float
    {
        $n = count($samples);
        if ($n === 0) {
            return 0.0;
        }

        return $this->countCorrect($samples) / $n;
    }

    private function resolveTenantId(\Illuminate\Support\Collection $items): string
    {
        $first = $items->first();

        return (string) ($first->session->tenant_id ?? $first->tenant_id ?? '');
    }
}
