<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Strategies;

use App\Domains\QuestionBank\DTOs\ItemResolutionRequest;
use App\Domains\QuestionBank\Repositories\QuestionPsychometricsRepository;
use Illuminate\Support\Collection;

class WeightedQuestionSelectionStrategy implements ItemResolutionStrategy
{
    /** Gaussian width for the difficulty-distance penalty (probability mass spread). */
    private const DIFFICULTY_SIGMA = 0.20;

    /** Floor weight for items below minDiscrimination — penalize but don't extinguish. */
    private const LOW_DISCRIMINATION_FLOOR = 0.01;

    /** Flat weight for uncalibrated items so they still appear in rotation for calibration. */
    private const UNCALIBRATED_BASE_WEIGHT = 0.10;

    public function __construct(
        private readonly QuestionPsychometricsRepository $psychometricsRepository,
    ) {
    }

    public function name(): string
    {
        return 'weighted';
    }

    public function resolve(ItemResolutionRequest $request, Collection $eligiblePool): Collection
    {
        if ($eligiblePool->isEmpty() || $request->itemCount <= 0) {
            return collect();
        }

        $versionIds = $eligiblePool
            ->pluck('version_id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        $metricsByVersion = $this->psychometricsRepository->getByVersionIds($versionIds);

        $weighted = $eligiblePool
            ->map(function ($item) use ($metricsByVersion, $request): array {
                $versionId = (string) $item->version_id;
                $metrics = $metricsByVersion[$versionId] ?? null;

                return [
                    'item' => $item,
                    'weight' => $this->computeWeight(
                        $metrics,
                        $request->targetDifficulty,
                        $request->minDiscrimination,
                    ),
                ];
            })
            ->filter(fn (array $entry): bool => $entry['weight'] > 0.0)
            ->values();

        return $this->weightedSampleWithoutReplacement($weighted, $request->itemCount);
    }

    /**
     * @param  array<string, mixed>|null  $metrics
     */
    private function computeWeight(?array $metrics, float $targetDifficulty, float $minDiscrimination): float
    {
        if ($metrics === null || ! ($metrics['is_calibrated'] ?? false)) {
            return self::UNCALIBRATED_BASE_WEIGHT;
        }

        $difficulty = (float) ($metrics['difficulty_index'] ?? 0.5);
        $discrimination = (float) (
            $metrics['point_biserial']
            ?? $metrics['discrimination_index']
            ?? 0.0
        );

        $delta = $difficulty - $targetDifficulty;
        $difficultyWeight = exp(-($delta * $delta) / (2.0 * self::DIFFICULTY_SIGMA * self::DIFFICULTY_SIGMA));

        $discriminationWeight = $discrimination < $minDiscrimination
            ? self::LOW_DISCRIMINATION_FLOOR
            : max($discrimination, self::LOW_DISCRIMINATION_FLOOR);

        return $difficultyWeight * $discriminationWeight;
    }

    /**
     * @param  Collection<int, array{item: mixed, weight: float}>  $weighted
     * @return Collection<int, mixed>
     */
    private function weightedSampleWithoutReplacement(Collection $weighted, int $count): Collection
    {
        $count = min($count, $weighted->count());
        $pool = $weighted->values()->all();
        $selected = collect();

        for ($i = 0; $i < $count && $pool !== []; $i++) {
            $totalWeight = 0.0;
            foreach ($pool as $entry) {
                $totalWeight += $entry['weight'];
            }
            if ($totalWeight <= 0.0) {
                break;
            }

            $threshold = (mt_rand() / mt_getrandmax()) * $totalWeight;
            $cumulative = 0.0;
            $pickedIndex = array_key_last($pool);

            foreach ($pool as $index => $entry) {
                $cumulative += $entry['weight'];
                if ($threshold <= $cumulative) {
                    $pickedIndex = $index;
                    break;
                }
            }

            $selected->push($pool[$pickedIndex]['item']);
            unset($pool[$pickedIndex]);
            $pool = array_values($pool);
        }

        return $selected;
    }
}
