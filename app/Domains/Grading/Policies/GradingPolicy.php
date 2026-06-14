<?php

declare(strict_types=1);

namespace App\Domains\Grading\Policies;

use App\Domains\Grading\Models\AnswerEvaluation;
use App\Domains\Grading\Models\AssessmentResult;
use App\Domains\Identity\Contracts\AuthorizationService;
use App\Domains\Identity\Models\User;

class GradingPolicy
{
    public function __construct(
        private readonly AuthorizationService $auth,
    ) {
    }

    /**
     * Can the actor list pending evaluations across any session?
     * Class-level gate — no model instance needed.
     * Granted to evaluators (grading.evaluate) and supervisors (grading.view).
     */
    public function listPending(User $actor): bool
    {
        return $this->hasPermission($actor, 'grading.evaluate')
            || $this->hasPermission($actor, 'grading.view');
    }

    /**
     * Can the actor view an individual evaluation row?
     * Scoped to the same tenant; readable by anyone with grading.view OR grading.evaluate.
     */
    public function viewEvaluation(User $actor, AnswerEvaluation $eval): bool
    {
        if (! $this->sameTenant($actor, $eval)) {
            return false;
        }

        return $this->hasPermission($actor, 'grading.evaluate')
            || $this->hasPermission($actor, 'grading.view');
    }

    /**
     * Can the actor submit a manual score for an evaluation?
     * Requires grading.evaluate — view-only access is insufficient.
     */
    public function submitEvaluation(User $actor, AnswerEvaluation $eval): bool
    {
        if (! $this->sameTenant($actor, $eval)) {
            return false;
        }

        return $this->hasPermission($actor, 'grading.evaluate');
    }

    /**
     * Can the actor publish a finalized assessment result for candidate visibility?
     * Requires grading.publish — view-only or evaluate access is insufficient.
     */
    public function publishResult(User $actor, AssessmentResult $result): bool
    {
        if (! $this->sameTenantForResult($actor, $result)) {
            return false;
        }

        return $this->hasPermission($actor, 'grading.publish');
    }

    /**
     * Can the actor inspect publication metadata (status, timestamps)?
     * Granted to anyone with grading.view or grading.publish.
     */
    public function viewPublicationStatus(User $actor, AssessmentResult $result): bool
    {
        if (! $this->sameTenantForResult($actor, $result)) {
            return false;
        }

        return $this->hasPermission($actor, 'grading.view')
            || $this->hasPermission($actor, 'grading.publish');
    }

    private function sameTenant(User $actor, AnswerEvaluation $eval): bool
    {
        return (string) $actor->tenant_id === (string) $eval->tenant_id;
    }

    private function sameTenantForResult(User $actor, AssessmentResult $result): bool
    {
        return (string) $actor->tenant_id === (string) $result->tenant_id;
    }

    private function hasPermission(User $actor, string $permission): bool
    {
        return $this->auth->userHasPermission(
            (string) $actor->tenant_id,
            (string) $actor->id,
            $permission,
        );
    }
}
