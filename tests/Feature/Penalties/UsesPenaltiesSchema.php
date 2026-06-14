<?php

declare(strict_types=1);

namespace Tests\Feature\Penalties;

use App\Domains\Grading\Models\Grade;
use App\Domains\Grading\Services\FinalGradeProcessingService;
use App\Domains\Penalties\Models\PenaltyRule;
use App\Domains\Penalties\Models\PenaltySanction;
use Illuminate\Support\Str;
use Tests\Feature\Grading\UsesGradingSchema;

trait UsesPenaltiesSchema
{
    use UsesGradingSchema;

    protected function createPenaltyRule(
        string $tenantId,
        string $createdByUserId,
        array $overrides = [],
    ): PenaltyRule {
        return PenaltyRule::query()->forceCreate(array_merge([
            'penalty_rule_id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'created_by_user_id' => $createdByUserId,
            'penalty_name' => 'Tab Switch Penalty',
            'penalty_type' => 'penalty',
            'trigger_condition' => 'proctor_event_type',
            'trigger_parameters' => ['event_type' => 'tab_switch'],
            'penalty_points' => 5.0,
            'is_cumulative' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    protected function createPenaltySanction(
        string $tenantId,
        string $sessionId,
        string $candidateId,
        string $penaltyRuleId,
        float $amount = 5.0,
        array $overrides = [],
    ): PenaltySanction {
        return PenaltySanction::query()->forceCreate(array_merge([
            'sanction_id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'session_id' => $sessionId,
            'candidate_user_id' => $candidateId,
            'penalty_rule_id' => $penaltyRuleId,
            'sanction_applied_at' => now(),
            'sanction_reason' => 'Test sanction',
            'sanction_amount' => $amount,
            'sanction_type' => 'penalty',
            'sanction_metadata' => ['source_event_id' => (string) Str::uuid()],
            'created_at' => now(),
        ], $overrides));
    }
}
