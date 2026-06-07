<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\Repositories;

use App\Domains\ExamSession\Exceptions\StaleVersionLockException;
use App\Domains\ExamSession\Models\CandidateExamStatus;
use App\Domains\ExamSession\Models\ExamCandidateEligible;
use App\Domains\ExamSession\Models\QuestionResponse;
use Illuminate\Support\Collection;

/**
 * Tenant isolation is enforced by explicit where('tenant_id') on every query.
 * Both CandidateExamStatus and ExamCandidateEligible use AutoFillsTenantId
 * (no global scope), so the repository is the primary isolation layer.
 *
 * Writes use forceCreate so server-controlled columns (tenant_id, UUIDs set by
 * the UsesUuid trait) persist correctly regardless of $fillable.
 *
 * Locking strategy:
 *   - findByIdForUpdate / findActiveSessionForUpdate → pessimistic (SELECT … FOR UPDATE), inside transaction
 *   - updateSession → optimistic (WHERE version_lock = expected), auto-throws on conflict
 */
class SessionRepository
{
    public function __construct(
        private readonly CandidateExamStatus $session,
        private readonly QuestionResponse $response,
        private readonly ExamCandidateEligible $enrollment,
    ) {
    }

    // -------------------------------------------------------------------------
    // Session reads
    // -------------------------------------------------------------------------

    public function findById(string $tenantId, string $sessionId): ?CandidateExamStatus
    {
        return $this->session
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->whereKey($sessionId)
            ->first();
    }

    /**
     * Pessimistic lock — must be called inside an active DB transaction.
     */
    public function findByIdForUpdate(string $tenantId, string $sessionId): ?CandidateExamStatus
    {
        return $this->session
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('session_id', $sessionId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Returns the first non-terminal session for this candidate/exam pair.
     * Used to make session starts idempotent.
     *
     * @param  array<int, string>  $activeStates
     */
    public function findActiveSession(
        string $tenantId,
        string $candidateId,
        string $examId,
        array $activeStates,
    ): ?CandidateExamStatus {
        return $this->session
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('candidate_user_id', $candidateId)
            ->where('exam_id', $examId)
            ->whereIn('session_state', $activeStates)
            ->whereNull('session_ended_at')
            ->first();
    }

    // -------------------------------------------------------------------------
    // Enrollment reads
    // -------------------------------------------------------------------------

    /**
     * Acquires an exclusive row-level pessimistic lock on the enrollment record.
     *
     * MUST be called inside an active DB::transaction. The lock prevents a
     * second concurrent startSession request for the same (tenant, candidate,
     * exam) from passing the idempotency check and the eligibility gates
     * simultaneously — they will serialize at this point, and the second
     * request will find the session created by the first after it acquires
     * the lock.
     *
     * On SQLite (used in the test suite) lockForUpdate() is a no-op at the
     * grammar level; the serialization guarantee applies only on MySQL/Postgres
     * in production.
     */
    public function findEnrollmentForUpdate(
        string $tenantId,
        string $candidateId,
        string $examId,
    ): ?ExamCandidateEligible {
        return $this->enrollment
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('candidate_user_id', $candidateId)
            ->where('exam_id', $examId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Returns the enrollment record for (tenant, candidate, exam) without
     * applying any eligibility filters. Used by the service to distinguish
     * "not enrolled at all" from "enrolled but ineligible".
     */
    public function findEnrollment(
        string $tenantId,
        string $candidateId,
        string $examId,
    ): ?ExamCandidateEligible {
        return $this->enrollment
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('candidate_user_id', $candidateId)
            ->where('exam_id', $examId)
            ->first();
    }

    /**
     * Returns the enrollment only when ALL eligibility conditions are satisfied:
     *   1. enrollment_status = 'active'
     *   2. now() is within the scheduling window (if window dates are set)
     *   3. attempts_used < max_attempts_allowed
     *
     * The cohort-membership check is intentionally excluded: it requires a join
     * into the Cohorts domain and is handled by the service after the enrollment
     * is confirmed to exist and be otherwise eligible.
     */
    public function findEnrollmentEligible(
        string $tenantId,
        string $candidateId,
        string $examId,
    ): ?ExamCandidateEligible {
        return $this->enrollment
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('candidate_user_id', $candidateId)
            ->where('exam_id', $examId)
            ->where('enrollment_status', 'active')
            ->where(
                fn ($q) => $q
                    ->whereNull('start_window_date')
                    ->orWhere('start_window_date', '<=', now())
            )
            ->where(
                fn ($q) => $q
                    ->whereNull('end_window_date')
                    ->orWhere('end_window_date', '>=', now())
            )
            ->whereColumn('attempts_used', '<', 'max_attempts_allowed')
            ->first();
    }

    // -------------------------------------------------------------------------
    // Response reads
    // -------------------------------------------------------------------------

    /**
     * @return Collection<int, QuestionResponse>
     */
    public function getAnswersSnapshot(string $tenantId, string $sessionId): Collection
    {
        return $this->response
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('session_id', $sessionId)
            ->orderBy('question_sequence_number')
            ->get();
    }

    // -------------------------------------------------------------------------
    // Writes
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createSession(array $attributes): CandidateExamStatus
    {
        $attributes['version_lock'] = $attributes['version_lock'] ?? 0;

        return $this->session->newQuery()->forceCreate($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function recordResponse(array $attributes): QuestionResponse
    {
        return $this->response->newQuery()->forceCreate($attributes);
    }

    /**
     * Record a heartbeat timestamp without touching version_lock.
     *
     * This is a deliberate bypass of the optimistic locking mechanism.
     * Heartbeats are a high-frequency side-channel signal; incrementing
     * version_lock on every heartbeat would cause concurrent response
     * submissions to fail with StaleVersionLockException.
     */
    public function updateHeartbeat(
        string $tenantId,
        string $sessionId,
        ?array $metadata = null,
    ): void {
        $payload = ['last_heartbeat_at' => now()];

        if ($metadata !== null) {
            $payload['heartbeat_metadata'] = $metadata;
        }

        $this->session
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('session_id', $sessionId)
            ->update($payload);
    }

    /**
     * Optimistic update — compares the in-memory version_lock against the
     * persisted row and atomically increments it on success.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws StaleVersionLockException when another process has already updated the row
     */
    public function updateSession(CandidateExamStatus $session, array $attributes): CandidateExamStatus
    {
        $expected = (int) $session->version_lock;
        $next = $expected + 1;

        $payload = array_merge($attributes, ['version_lock' => $next]);

        $affected = $this->session
            ->newQuery()
            ->where('session_id', $session->session_id)
            ->where('version_lock', $expected)
            ->update($payload);

        if ($affected === 0) {
            throw StaleVersionLockException::forSession((string) $session->session_id, $expected);
        }

        $session->forceFill($payload);
        $session->syncOriginal();

        return $session;
    }
}
