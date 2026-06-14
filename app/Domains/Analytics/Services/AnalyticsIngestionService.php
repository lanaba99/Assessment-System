<?php

declare(strict_types=1);

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\Models\AnalyticsCache;
use App\Domains\Grading\Events\ResultGenerated;

class AnalyticsIngestionService
{
    public function ingest(ResultGenerated $event): void
    {
        $summary = $event->summary;
        $cacheKey = 'result_finalized:' . $summary->sessionId;

        AnalyticsCache::query()->updateOrCreate(
            [
                'tenant_id' => $summary->tenantId,
                'cache_key' => $cacheKey,
            ],
            [
                'cache_value' => [
                    'session_id' => $summary->sessionId,
                    'candidate_id' => $summary->candidateId,
                    'exam_id' => $summary->examId,
                    'percentage' => $summary->percentage,
                    'is_passing' => $summary->isPassing,
                    'is_first_finalization' => $event->isFirstFinalization,
                    'calculated_at' => $event->calculatedAt->format(\DateTimeInterface::ATOM),
                ],
                'computed_at' => now(),
                'expires_at' => now()->addDays(30),
            ],
        );

        $this->refreshDashboardSummary($summary->tenantId);
    }

    private function refreshDashboardSummary(string $tenantId): void
    {
        $results = AnalyticsCache::query()
            ->where('tenant_id', $tenantId)
            ->where('cache_key', 'like', 'result_finalized:%')
            ->get();

        $percentages = $results
            ->map(fn (AnalyticsCache $entry): float => (float) ($entry->cache_value['percentage'] ?? 0.0))
            ->filter(fn (float $value): bool => $value > 0.0);

        $average = $percentages->isEmpty()
            ? 0.0
            : round($percentages->avg(), 2);

        AnalyticsCache::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'cache_key' => 'dashboard:summary',
            ],
            [
                'cache_value' => [
                    'total_finalized_results' => $results->count(),
                    'average_percentage' => $average,
                ],
                'computed_at' => now(),
                'expires_at' => now()->addHour(),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getDashboardSummary(string $tenantId): array
    {
        $entry = AnalyticsCache::query()
            ->where('tenant_id', $tenantId)
            ->where('cache_key', 'dashboard:summary')
            ->first();

        if ($entry === null || ! is_array($entry->cache_value)) {
            return [
                'total_finalized_results' => 0,
                'average_percentage' => 0.0,
            ];
        }

        return $entry->cache_value;
    }
}
