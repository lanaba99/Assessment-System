<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\Policies;

use App\Domains\ExamSession\Models\CandidateExamStatus;
use App\Domains\ExamSession\States\ActiveState;
use App\Domains\ExamSession\States\CompletedState;
use App\Domains\ExamSession\States\TerminatedState;
use App\Domains\Identity\Contracts\AuthorizationService;
use App\Domains\Identity\Enums\RoleName;
use App\Domains\Identity\Models\User;

class ExamSessionPolicy
{
    public function __construct(
        private readonly AuthorizationService $auth,
    ) {
    }

    /**
     * Can the actor start a new session? Requires exam_sessions.start permission.
     * The eligibility gate (enrollment, window, attempts, cohort) is enforced in
     * the service layer — this method only checks the actor's role-based access.
     */
    public function start(User $actor): bool
    {
        return $this->hasPermission($actor, 'exam_sessions.start');
    }

    /**
     * Can the actor list sessions (GET /exam-sessions), optionally scoped to
     * a single ?status= filter?
     *
     * Requires exam_sessions.view as a baseline gate, then narrows by role:
     *   - Tenant Admin        → any status, or the unfiltered list.
     *   - Proctor              → only in_progress / terminated (live monitoring
     *                            + integrity review). No status, or any other
     *                            status, is denied — a Proctor must not browse
     *                            completed/graded sessions.
     *   - Technical Evaluator  → only completed (grading/publishing/certs).
     *     No status, or any other status, is denied.
     *
     * $status is the raw ?status= query value (already validated upstream
     * against the known state names), or null when the caller asked for the
     * unfiltered list.
     */
    public function viewAny(User $actor, ?string $status = null): bool
    {
        if (! $this->hasPermission($actor, 'exam_sessions.view')) {
            return false;
        }

        if ($this->hasRole($actor, RoleName::TenantAdmin)) {
            return true;
        }

        if ($this->hasRole($actor, RoleName::Proctor)) {
            return in_array($status, [ActiveState::NAME, TerminatedState::NAME], true);
        }

        if ($this->hasRole($actor, RoleName::TechnicalEvaluator)) {
            return $status === CompletedState::NAME;
        }

        // Any other role holding exam_sessions.view without a recognised
        // role above falls back to denied — this endpoint is admin/staff-only
        // by design, not a general-purpose grant.
        return false;
    }

    /**
     * Can the actor view this session?
     * - The session owner can always view their own session.
     * - Admins/proctors with exam_sessions.view can view any session on the same tenant.
     */
    public function view(User $actor, CandidateExamStatus $session): bool
    {
        if (! $this->sameTenant($actor, $session)) {
            return false;
        }

        return $this->ownsSession($actor, $session)
            || $this->hasPermission($actor, 'exam_sessions.view');
    }

    /**
     * Can the actor perform candidate-level actions on this session (submit,
     * suspend, resume, complete)?
     *
     * Grants access when the actor either:
     *   - owns the session (the enrolled candidate), OR
     *   - holds exam_sessions.manage (proctors intervening in emergencies)
     *
     * Note: the service layer applies an additional actor-aware guard for
     * complete/terminate — a manager acting on a zero-response session will
     * not trigger the grading pipeline, preventing ghost grade records.
     */
    public function participate(User $actor, CandidateExamStatus $session): bool
    {
        if (! $this->sameTenant($actor, $session)) {
            return false;
        }

        return $this->ownsSession($actor, $session)
            || $this->hasPermission($actor, 'exam_sessions.manage');
    }

    /**
     * Can the actor forcibly terminate this session?
     * Requires exam_sessions.manage (admin/proctor-level permission).
     */
    public function manage(User $actor, CandidateExamStatus $session): bool
    {
        if (! $this->sameTenant($actor, $session)) {
            return false;
        }

        return $this->hasPermission($actor, 'exam_sessions.manage');
    }

    /**
     * Can the actor create, list, or revoke enrollments?
     * Class-level check (no model instance needed).
     */
    public function manageEnrollments(User $actor): bool
    {
        return $this->hasPermission($actor, 'enrollments.manage');
    }

    private function ownsSession(User $actor, CandidateExamStatus $session): bool
    {
        return (string) $actor->id === (string) $session->candidate_user_id;
    }

    private function hasRole(User $actor, RoleName $role): bool
    {
        return $this->auth->userHasRole(
            (string) $actor->tenant_id,
            (string) $actor->id,
            $role->value,
        );
    }

    private function hasPermission(User $actor, string $permission): bool
    {
        return $this->auth->userHasPermission(
            (string) $actor->tenant_id,
            (string) $actor->id,
            $permission,
        );
    }

    private function sameTenant(User $actor, CandidateExamStatus $session): bool
    {
        return (string) $actor->tenant_id === (string) $session->tenant_id;
    }
}