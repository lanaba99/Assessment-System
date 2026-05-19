<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Strategies;

use App\Domains\QuestionBank\DTOs\ItemResolutionRequest;
use Illuminate\Support\Collection;

class RandomWithinBucketsStrategy implements ItemResolutionStrategy
{
    public function name(): string
    {
        return 'random';
    }

    public function resolve(ItemResolutionRequest $request, Collection $eligiblePool): Collection
    {
        $byCompetency = $eligiblePool->groupBy(
            fn ($version): ?string => $this->primaryCompetencyId($version),
        );

        $selected = new Collection();
        $remaining = $request->itemCount;

        foreach ($request->competencyWeights as $competencyId => $weightPercentage) {
            $quota = (int) round($request->itemCount * ($weightPercentage / 100));

            if ($quota === 0) {
                continue;
            }

            $bucket = $byCompetency->get($competencyId, new Collection());
            $picks = $bucket->shuffle()->take($quota);
            $selected = $selected->concat($picks);
            $remaining -= $picks->count();
        }

        if ($remaining > 0) {
            $selectedIds = $selected->pluck('version_id')->all();
            $filler = $eligiblePool
                ->whereNotIn('version_id', $selectedIds)
                ->shuffle()
                ->take($remaining);
            $selected = $selected->concat($filler);
        }

        return $selected->values();
    }

    private function primaryCompetencyId(object $version): ?string
    {
        $competencies = $version->question?->competencies ?? new Collection();

        $primary = $competencies->first(
            static fn ($competency) => (bool) ($competency->pivot->is_primary_competency ?? false),
        );

        return ($primary ?? $competencies->first())?->competency_id;
    }
}
