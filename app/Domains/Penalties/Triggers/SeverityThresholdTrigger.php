<?php

declare(strict_types=1);

namespace App\Domains\Penalties\Triggers;

use App\Domains\Penalties\Contracts\PenaltyTrigger;
use App\Domains\Penalties\Models\PenaltyRule;
use App\Domains\Proctoring\Events\ProctorEventLogged;

class SeverityThresholdTrigger implements PenaltyTrigger
{
    public const TYPE = 'proctor_severity_minimum';

    private const SEVERITY_RANK = [
        'info' => 1,
        'low' => 2,
        'medium' => 3,
        'high' => 4,
        'critical' => 5,
    ];

    public function supports(string $triggerCondition): bool
    {
        return $triggerCondition === self::TYPE;
    }

    public function matches(PenaltyRule $rule, ProctorEventLogged $event): bool
    {
        if ($event->severityLevel === null) {
            return false;
        }

        $params = is_array($rule->trigger_parameters) ? $rule->trigger_parameters : [];
        $minSeverity = $params['min_severity'] ?? null;

        if ($minSeverity === null) {
            return false;
        }

        $eventRank = self::SEVERITY_RANK[strtolower($event->severityLevel)] ?? 0;
        $thresholdRank = self::SEVERITY_RANK[strtolower((string) $minSeverity)] ?? PHP_INT_MAX;

        return $eventRank >= $thresholdRank;
    }
}
