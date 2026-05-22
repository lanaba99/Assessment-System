<?php

declare(strict_types=1);

namespace App\Domains\Penalties\Triggers;

use App\Domains\Penalties\Contracts\PenaltyTrigger;
use App\Domains\Penalties\Models\PenaltyRule;
use App\Domains\Proctoring\Events\ProctorEventLogged;

class ProctorEventTypeTrigger implements PenaltyTrigger
{
    public const TYPE = 'proctor_event_type';

    public function supports(string $triggerCondition): bool
    {
        return $triggerCondition === self::TYPE;
    }

    public function matches(PenaltyRule $rule, ProctorEventLogged $event): bool
    {
        $params = is_array($rule->trigger_parameters) ? $rule->trigger_parameters : [];

        $expected = $params['event_type'] ?? $params['event_types'] ?? null;

        if ($expected === null) {
            return false;
        }

        if (is_array($expected)) {
            return in_array($event->eventType, array_map('strval', $expected), true);
        }

        return $event->eventType === (string) $expected;
    }
}
