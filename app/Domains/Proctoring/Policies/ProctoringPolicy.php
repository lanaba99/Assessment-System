<?php

declare(strict_types=1);

namespace App\Domains\Proctoring\Policies;

use App\Domains\ExamSession\Repositories\SessionRepository;
use App\Domains\ExamSession\States\ExamSessionStateFactory;
use App\Domains\Identity\Contracts\AuthorizationService;
use App\Domains\Identity\Models\User;

class ProctoringPolicy
{
    public function __construct(
        private readonly AuthorizationService $auth,
        private readonly SessionRepository $sessions,
        private readonly ExamSessionStateFactory $stateFactory,
    ) {
    }

    /**
     * who can ingest proctoring events for a session?
     * given to the proctoring tool (proctoring.ingest permission) OR to the
     * candidate's own device/browser agent for their own active session only.
     */

    public function viewForSession(User $actor): bool
    {
        return $this->hasPermission($actor, 'proctoring.view');
    }

    private function hasPermission(User $actor, string $permission): bool
    {
        return $this->auth->userHasPermission(
            (string) $actor->tenant_id,
            (string) $actor->id,
            $permission,
        );
    }

    /**
     * Existing behavior unchanged: any actor holding proctoring.ingest
     * (the proctoring tool's system permission) may always ingest.
     *
     * New: a candidate with no such permission may still ingest, but only
     * for a session that (a) exists on their own tenant, (b) belongs to
     * them, and (c) is not already in a terminal state. This does not grant
     * proctoring.ingest at the role level — it's a narrow, per-session
     * ownership check evaluated here.
     */
    public function ingestEvents(User $user, string $sessionId): bool
    {
        if ($this->hasPermission($user, 'proctoring.ingest')) {
            return true;
        }

        $session = $this->sessions->findById((string) $user->tenant_id, $sessionId);

        if ($session === null) {
            return false;
        }

        if ((string) $session->candidate_user_id !== (string) $user->id) {
            return false;
        }

        return ! $this->stateFactory->fromSession($session)->isTerminal();
    }
}