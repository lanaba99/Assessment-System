<?php

declare(strict_types=1);

namespace App\Domains\Penalties\Contracts;

use App\Domains\Penalties\Models\PenaltyRule;
use App\Domains\Proctoring\Events\ProctorEventLogged;

interface PenaltyTrigger
{
    public function supports(string $triggerCondition): bool;

    public function matches(PenaltyRule $rule, ProctorEventLogged $event): bool;
}
