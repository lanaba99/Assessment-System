<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Strategies;

use App\Domains\QuestionBank\DTOs\ItemResolutionRequest;
use Illuminate\Support\Collection;

class StratifiedBloomStrategy implements ItemResolutionStrategy
{
    public function name(): string
    {
        return 'stratified';
    }

    public function resolve(ItemResolutionRequest $request, Collection $eligiblePool): Collection
    {
        $bloomQuotas = $this->computeBloomQuotas($request);
        $competencyQuotas = $this->computeCompetencyQuotas($request);

        $byCompetencyAndBloom = $eligiblePool->groupBy([
            fn ($version): ?string => $this->primaryCompetencyId($version),
            fn ($version): int => (int) ($version->question->cognitive_level ?? 0),
        ]);

        $selected = new Collection();
        $selectedIds = [];

        foreach ($competencyQuotas as $competencyId => $competencyQuota) {
            foreach ($bloomQuotas as $bloomLevel => $bloomShare) {
                $cellQuota = (int) round($competencyQuota * $bloomShare);
                if ($cellQuota === 0) {
                    continue;
                }

                $cell = $byCompetencyAndBloom->get($competencyId, new Collection())->get($bloomLevel, new Collection());
                $picks = $cell
                    ->whereNotIn('version_id', $selectedIds)
                    ->sortByDesc(fn ($v): float => $this->discriminationFor($v))
                    ->take($cellQuota);

                $selected = $selected->concat($picks);
                $selectedIds = $selected->pluck('version_id')->all();
            }
        }

        $remaining = $request->itemCount - $selected->count();
        if ($remaining > 0) {
            $filler = $eligiblePool
                ->whereNotIn('version_id', $selectedIds)
                ->sortByDesc(fn ($v): float => $this->discriminationFor($v))
                ->take($remaining);
            $selected = $selected->concat($filler);
        }

        return $selected->values();
    }

    private function computeBloomQuotas(ItemResolutionRequest $request): array
    {
        $total = array_sum($request->bloomDistribution);
        if ($total <= 0) {
            return [];
        }

        $normalized = [];
        foreach ($request->bloomDistribution as $bloomLevel => $share) {
            $normalized[$bloomLevel] = $share / $total;
        }

        return $normalized;
    }

    private function computeCompetencyQuotas(ItemResolutionRequest $request): array
    {
        $quotas = [];
        foreach ($request->competencyWeights as $competencyId => $weightPercentage) {
            $quotas[$competencyId] = (int) round($request->itemCount * ($weightPercentage / 100));
        }

        return $quotas;
    }

    private function primaryCompetencyId(object $version): ?string
    {
        $competencies = $version->question?->competencies ?? new Collection();

        $primary = $competencies->first(
            static fn ($competency) => (bool) ($competency->pivot->is_primary_competency ?? false),
        );

        return ($primary ?? $competencies->first())?->competency_id;
    }

    private function discriminationFor(object $version): float
    {
        return (float) ($version->psychometrics?->discrimination_index ?? 0.0);
    }
}
