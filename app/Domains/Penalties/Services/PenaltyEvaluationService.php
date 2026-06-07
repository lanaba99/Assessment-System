<?php

declare(strict_types=1);

namespace App\Domains\Penalties\Services;

use App\Domains\Penalties\Contracts\PenaltyTrigger;
use App\Domains\Penalties\DTOs\AppliedSanction;
use App\Domains\Penalties\Models\PenaltyRule;
use App\Domains\Penalties\Repositories\PenaltyRuleRepository;
use App\Domains\Penalties\Repositories\PenaltySanctionRepository;
use App\Domains\Proctoring\Events\ProctorEventLogged;
use Illuminate\Support\Facades\DB;

class PenaltyEvaluationService
{
    public function __construct(
        private readonly PenaltyRuleRepository $rules,
        private readonly PenaltySanctionRepository $sanctions,
        /** @var iterable<PenaltyTrigger> */
        private readonly iterable $triggers,
    ) {
    }

    /**
     * Evaluate every active PenaltyRule for this event's tenant and apply any matches.
     *
     * @return array<int, AppliedSanction>
     */
    public function evaluateProctorEvent(ProctorEventLogged $event): array
    {
        $rules = $this->rules->findActiveForTenant($event->tenantId);

        if ($rules->isEmpty()) {
            return [];
        }

        $applied = [];

        foreach ($rules as $rule) {
            $trigger = $this->resolveTrigger((string) $rule->trigger_condition);

            if ($trigger === null || ! $trigger->matches($rule, $event)) {
                continue;
            }

            $sanction = $this->applyAtomically($rule, $event);

            if ($sanction !== null) {
                $applied[] = $sanction;
            }
        }

        return $applied;
    }

    private function applyAtomically(PenaltyRule $rule, ProctorEventLogged $event): ?AppliedSanction
    {
        return DB::transaction(function () use ($rule, $event): ?AppliedSanction {
            $ruleId = (string) $rule->penalty_rule_id;

            // Idempotency: same rule + same session + same source event → skip.
            if ($this->sanctions->existsForSessionRuleAndEvent($event->tenantId, $event->sessionId, $ruleId, $event->eventId)) {
                return null;
            }

            $amount = $this->resolveAmount($rule);
            $sanctionType = (string) ($rule->penalty_type ?? 'penalty');
            $reason = $this->buildReason($rule, $event);

            $sanction = $this->sanctions->create([
                'session_id' => $event->sessionId,
                'candidate_user_id' => $event->candidateId,
                'penalty_rule_id' => $ruleId,
                'tenant_id' => $event->tenantId,
                'sanction_applied_at' => now(),
                'sanction_reason' => $reason,
                'sanction_amount' => $amount,
                'sanction_type' => $sanctionType,
                'sanction_metadata' => [
                    'source_event_id' => $event->eventId,
                    'source_event_type' => $event->eventType,
                    'source_event_category' => $event->eventCategory,
                    'source_severity_level' => $event->severityLevel,
                    'trigger_condition' => (string) $rule->trigger_condition,
                    'rule_name' => (string) $rule->penalty_name,
                ],
                'created_at' => now(),
            ]);

            return new AppliedSanction(
                sanctionId: (string) $sanction->sanction_id,
                penaltyRuleId: $ruleId,
                sessionId: $event->sessionId,
                candidateId: $event->candidateId,
                sanctionType: $sanctionType,
                sanctionAmount: $amount,
                reason: $reason,
            );
        });
    }

    private function resolveTrigger(string $triggerCondition): ?PenaltyTrigger
    {
        foreach ($this->triggers as $trigger) {
            if ($trigger->supports($triggerCondition)) {
                return $trigger;
            }
        }

        return null;
    }

    private function resolveAmount(PenaltyRule $rule): float
    {
        if ($rule->penalty_points !== null) {
            return (float) $rule->penalty_points;
        }

        if ($rule->penalty_percentage !== null) {
            return (float) $rule->penalty_percentage;
        }

        return 0.0;
    }

    private function buildReason(PenaltyRule $rule, ProctorEventLogged $event): string
    {
        return sprintf(
            "Rule '%s' triggered by proctor event '%s' (severity: %s).",
            (string) $rule->penalty_name,
            $event->eventType,
            $event->severityLevel ?? 'n/a',
        );
    }
}
