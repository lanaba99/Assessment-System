<?php

declare(strict_types=1);

namespace Tests\Feature\ExamSession;

use App\Domains\Cohorts\Models\Cohort;
use App\Domains\Cohorts\Models\CohortMember;
use App\Domains\ExamEngine\Models\ExamSection;
use App\Domains\ExamSession\Models\CandidateExamStatus;
use App\Domains\ExamSession\Models\ExamCandidateEligible;
use App\Domains\ExamSession\Models\ExamSessionItem;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Feature\ExamEngine\UsesExamEngineSchema;

/**
 * Builds the complete ExamSession table stack on top of the ExamEngine schema.
 *
 * All session-related tables are defined inline (rather than loading production
 * migration files) for two reasons:
 *
 *   1. exam_session_items has a FK to question_versions which requires the full
 *      QuestionBank stack — not needed for focused session tests.
 *   2. Two alter migrations promote version_lock from timestamp → unsignedBigInteger.
 *      SQLite column modification is unreliable; the inline schema uses the final
 *      shape directly.
 *
 * Inline table definitions must be kept in lockstep with the production migrations
 * and alter files. The schema drift guard in ExamSessionLifecycleTest catches any
 * column additions/removals.
 *
 * Tables built (dependency order):
 *   question_versions (stub) → cohorts → cohort_members →
 *   exam_enrollments → exam_sessions → exam_session_items
 *
 * Tables inherited from UsesExamEngineSchema (via UsesIdentitySchema):
 *   users, roles, permissions, … exams, exam_sections, exam_blueprints
 */
trait UsesExamSessionSchema
{
    use UsesExamEngineSchema;

    protected function bootExamSessionSchema(): void
    {
        $this->bootExamEngineSchema();
        $this->migrateExamSessionTables();
    }

    private function migrateExamSessionTables(): void
    {
        $connection = (string) config('database.default');

        // For persistent connections (MySQL), tear down in reverse FK order so
        // each test begins from a clean schema. SQLite uses a fresh in-memory DB
        // per beforeEach (DB::purge + reconnect), so teardown is a no-op there.
        if ($connection !== 'sqlite') {
            Schema::connection($connection)->disableForeignKeyConstraints();

            foreach ([
                'question_responses', 'exam_session_items', 'exam_sessions',
                'exam_enrollments', 'cohort_members', 'cohorts', 'question_versions',
            ] as $table) {
                Schema::connection($connection)->dropIfExists($table);
            }

            Schema::connection($connection)->enableForeignKeyConstraints();
        }

        // ── question_versions (stub) ──────────────────────────────────────────
        // Only version_id is needed to satisfy the exam_session_items FK.
        // Full QuestionBank columns are intentionally excluded — they are
        // irrelevant to session lifecycle tests.
        Schema::create('question_versions', function (Blueprint $table): void {
            $table->uuid('version_id')->primary();
            $table->uuid('tenant_id')->nullable();
            // QuestionVersion uses SoftDeletes; the model appends deleted_at to queries.
            $table->softDeletes();
        });

        // ── cohorts ───────────────────────────────────────────────────────────
        Schema::create('cohorts', function (Blueprint $table): void {
            $table->uuid('cohort_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('parent_cohort_id')->nullable();
            $table->uuid('created_by_user_id');
            $table->string('cohort_name');
            $table->string('cohort_code')->unique();
            $table->string('cohort_type');
            $table->text('cohort_description')->nullable();
            $table->unsignedInteger('hierarchy_level')->default(0);
            $table->json('cohort_attributes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('created_by_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')->onDelete('restrict');
        });

        // ── cohort_members ────────────────────────────────────────────────────
        Schema::create('cohort_members', function (Blueprint $table): void {
            $table->uuid('member_id')->primary();
            $table->uuid('cohort_id');
            $table->uuid('user_id');
            $table->uuid('tenant_id');
            $table->string('membership_role')->default('member');
            $table->timestamp('added_at')->useCurrent();
            $table->timestamp('removed_at')->nullable();
            $table->boolean('is_active_member')->default(true);

            $table->foreign('cohort_id')
                ->references('cohort_id')->on('cohorts')
                ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')->onDelete('cascade');

            $table->unique(['cohort_id', 'user_id']);
        });

        // ── exam_enrollments ──────────────────────────────────────────────────
        Schema::create('exam_enrollments', function (Blueprint $table): void {
            $table->uuid('enrollment_id')->primary();
            $table->uuid('exam_id');
            $table->uuid('candidate_user_id');
            $table->uuid('tenant_id');
            $table->uuid('cohort_id')->nullable();

            $table->string('enrollment_status')->default('pending');

            $table->dateTime('enrollment_date')->nullable();
            $table->dateTime('start_window_date')->nullable();
            $table->dateTime('end_window_date')->nullable();
            $table->dateTime('start_eligibility_date')->nullable();
            $table->dateTime('end_eligibility_date')->nullable();

            $table->boolean('can_retake_exam')->default(false);
            $table->unsignedInteger('max_attempts_allowed')->default(1);
            $table->unsignedInteger('attempts_used')->default(0);
            $table->unsignedInteger('attempts_remaining')->default(1);

            $table->decimal('highest_score_achieved', 6, 2)->nullable();
            $table->string('highest_score_status')->nullable();
            $table->text('enrollment_notes')->nullable();

            $table->timestamps();

            $table->foreign('exam_id')
                ->references('exam_id')->on('exams')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('candidate_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')->onDelete('cascade');

            $table->unique(['exam_id', 'candidate_user_id']);
            $table->index('tenant_id');
        });

        // ── exam_sessions ─────────────────────────────────────────────────────
        // Final schema: version_lock is unsignedBigInteger (after the alter
        // migration), heartbeat columns included.
        Schema::create('exam_sessions', function (Blueprint $table): void {
            $table->uuid('session_id')->primary();
            $table->uuid('exam_id');
            $table->uuid('enrollment_id');
            $table->uuid('candidate_user_id');
            $table->uuid('tenant_id');
            $table->uuid('proctor_user_id')->nullable();

            $table->string('session_state')->default('not_started');

            $table->string('current_question_reference')->nullable();
            $table->unsignedInteger('current_question_index')->default(0);
            $table->unsignedInteger('total_questions_responded')->default(0);
            $table->unsignedInteger('total_questions_flagged')->default(0);

            $table->json('session_progress_json')->nullable();
            $table->json('candidate_device_metadata')->nullable();

            $table->string('device_fingerprint')->nullable();
            $table->string('device_id')->nullable();
            $table->string('device_type')->nullable();
            $table->string('browser_type')->nullable();
            $table->string('operating_system')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('initial_ip_address', 45)->nullable();

            $table->decimal('gps_latitude', 10, 7)->nullable();
            $table->decimal('gps_longitude', 10, 7)->nullable();
            $table->string('session_start_location')->nullable();

            $table->dateTime('session_started_at')->nullable();
            $table->dateTime('session_resumed_at')->nullable();
            $table->dateTime('session_ended_at')->nullable();

            $table->unsignedInteger('total_session_duration_seconds')->default(0);
            $table->unsignedInteger('actual_response_time_seconds')->default(0);

            $table->string('completion_method')->nullable();

            // Heartbeat columns (from 2026_05_19_000025 migration)
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->json('heartbeat_metadata')->nullable();

            // Final shape after 2026_05_22 alter migration
            $table->unsignedBigInteger('version_lock')->default(0);

            $table->timestamps();

            $table->foreign('exam_id')
                ->references('exam_id')->on('exams')
                ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('enrollment_id')
                ->references('enrollment_id')->on('exam_enrollments')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('candidate_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')->onDelete('cascade');

            $table->index('tenant_id');
            $table->index('session_state');
        });

        // ── question_responses ────────────────────────────────────────────────
        // No Eloquent timestamps (QuestionResponse::$timestamps = false).
        Schema::create('question_responses', function (Blueprint $table): void {
            $table->uuid('response_id')->primary();
            $table->uuid('session_id');
            $table->uuid('question_version_id');
            $table->uuid('candidate_user_id');
            $table->uuid('tenant_id');

            $table->unsignedInteger('question_sequence_number');
            $table->string('response_type');

            $table->json('response_data')->nullable();
            $table->text('response_text')->nullable();
            $table->json('selected_options_json')->nullable();
            $table->string('file_upload_url', 1024)->nullable();
            $table->unsignedInteger('time_spent_seconds')->nullable();
            $table->unsignedInteger('time_elapsed_from_start_seconds')->nullable();

            $table->boolean('is_flagged_for_review')->default(false);
            $table->boolean('is_correct')->nullable();
            $table->decimal('raw_score', 6, 2)->nullable();
            $table->decimal('normalized_score', 6, 2)->nullable();
            $table->decimal('final_score', 6, 2)->nullable();

            $table->json('scoring_metadata')->nullable();
            $table->string('integrity_status')->nullable();
            $table->json('response_metadata')->nullable();

            $table->timestamp('response_submitted_at')->nullable();

            $table->foreign('session_id')
                ->references('session_id')->on('exam_sessions')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('question_version_id')
                ->references('version_id')->on('question_versions')
                ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('candidate_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')->onDelete('cascade');

            $table->index('tenant_id');
        });

        // ── exam_session_items ────────────────────────────────────────────────
        // Final shape: version_lock is unsignedBigInteger.
        Schema::create('exam_session_items', function (Blueprint $table): void {
            $table->uuid('session_item_id')->primary();
            $table->uuid('session_id');
            $table->uuid('section_id');
            $table->uuid('question_version_id');

            $table->unsignedInteger('sequence_number');
            $table->string('item_state')->default('pending');

            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamp('answered_at')->nullable();

            $table->boolean('is_flagged')->default(false);

            // Final shape after 2026_05_22 alter migration
            $table->unsignedBigInteger('version_lock')->default(0);

            $table->timestamps();

            $table->foreign('session_id')
                ->references('session_id')->on('exam_sessions')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('section_id')
                ->references('section_id')->on('exam_sections')
                ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('question_version_id')
                ->references('version_id')->on('question_versions')
                ->onUpdate('cascade')->onDelete('restrict');

            $table->unique(['session_id', 'sequence_number']);
            $table->index('item_state');
        });
    }

    // =========================================================================
    // Composite setup helper
    // =========================================================================

    /**
     * Creates a published exam + one section + one question_version stub, then
     * mocks QuestionSelectionService to return that single item. Call this at
     * the start of any test that exercises startSession.
     *
     * Returns [$exam, $section, $versionId].
     *
     * Public so it is accessible from global Pest helper functions.
     */
    public function prepareExamWithMockedItems(
        string $tenantId,
        string $userId,
    ): array {
        $exam = $this->createExam($tenantId, $userId, [
            'exam_status' => \App\Domains\ExamEngine\Enums\ExamStatus::Published,
            'is_published' => true,
            'is_adaptive_exam' => false,
        ]);

        $section = $this->createExamSection((string) $exam->exam_id, $tenantId);
        $versionId = $this->createQuestionVersionStub($tenantId);

        $this->mock(\App\Domains\ExamEngine\Contracts\QuestionSelectionService::class)
            ->shouldReceive('resolveQuestionsForSession')
            ->andReturn(collect([(object) [
                'sectionId' => (string) $section->section_id,
                'questionVersionId' => $versionId,
            ]]));

        return [$exam, $section, $versionId];
    }

    // =========================================================================
    // Setup helpers — for test data creation only, never for assertion paths
    // =========================================================================

    /**
     * Insert a minimal stub row into question_versions and return its UUID.
     * Used to satisfy the exam_session_items.question_version_id FK constraint.
     */
    protected function createQuestionVersionStub(string $tenantId): string
    {
        $versionId = (string) Str::uuid();

        DB::table('question_versions')->insert([
            'version_id' => $versionId,
            'tenant_id' => $tenantId,
        ]);

        return $versionId;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createExamSection(string $examId, string $tenantId, array $overrides = []): ExamSection
    {
        return ExamSection::query()->forceCreate(array_merge([
            'section_id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'exam_id' => $examId,
            'section_name' => 'Test Section',
            'section_code' => 'SEC-' . strtoupper(Str::random(6)),
            'section_sequence' => 1,
            'questions_in_section' => 10,
            'time_limit_minutes' => null,
            'branching_logic' => null,
            'section_metadata' => null,
        ], $overrides));
    }

    /**
     * Create an active enrollment that passes all eligibility gates by default.
     * Override specific fields to test failure conditions.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function createEnrollment(
        string $tenantId,
        string $examId,
        string $candidateUserId,
        array $overrides = [],
    ): ExamCandidateEligible {
        return ExamCandidateEligible::query()->forceCreate(array_merge([
            'enrollment_id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'exam_id' => $examId,
            'candidate_user_id' => $candidateUserId,
            'cohort_id' => null,
            'enrollment_status' => 'active',
            'enrollment_date' => now(),
            'start_window_date' => null,
            'end_window_date' => null,
            'start_eligibility_date' => null,
            'end_eligibility_date' => null,
            'can_retake_exam' => false,
            'max_attempts_allowed' => 1,
            'attempts_used' => 0,
            'attempts_remaining' => 1,
            'highest_score_achieved' => null,
            'highest_score_status' => null,
            'enrollment_notes' => null,
        ], $overrides));
    }

    /**
     * Create a session directly — use this only for pre-seeding state before
     * testing downstream operations. Business-logic tests must go through HTTP.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function createExamSession(
        string $tenantId,
        string $examId,
        string $enrollmentId,
        string $candidateUserId,
        array $overrides = [],
    ): CandidateExamStatus {
        return CandidateExamStatus::query()->forceCreate(array_merge([
            'session_id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'exam_id' => $examId,
            'enrollment_id' => $enrollmentId,
            'candidate_user_id' => $candidateUserId,
            'session_state' => 'in_progress',
            'session_started_at' => now(),
            'total_questions_responded' => 0,
            'total_questions_flagged' => 0,
            'version_lock' => 0,
        ], $overrides));
    }

    /**
     * Create a session item for a given session. The section and question_version
     * must already exist in the database (use createExamSection and
     * createQuestionVersionStub to set them up).
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function createSessionItem(
        string $sessionId,
        string $sectionId,
        string $questionVersionId,
        array $overrides = [],
    ): ExamSessionItem {
        return ExamSessionItem::query()->forceCreate(array_merge([
            'session_item_id' => (string) Str::uuid(),
            'session_id' => $sessionId,
            'section_id' => $sectionId,
            'question_version_id' => $questionVersionId,
            'sequence_number' => 1,
            'item_state' => 'pending',
            'is_flagged' => false,
            'version_lock' => 0,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createCohort(
        string $tenantId,
        string $createdByUserId,
        array $overrides = [],
    ): Cohort {
        return Cohort::query()->forceCreate(array_merge([
            'cohort_id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'created_by_user_id' => $createdByUserId,
            'cohort_name' => 'Test Cohort ' . Str::random(6),
            'cohort_code' => 'COH-' . strtoupper(Str::random(8)),
            'cohort_type' => 'team',
            'hierarchy_level' => 0,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createCohortMember(
        string $tenantId,
        string $cohortId,
        string $userId,
        array $overrides = [],
    ): CohortMember {
        return CohortMember::query()->forceCreate(array_merge([
            'member_id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'cohort_id' => $cohortId,
            'user_id' => $userId,
            'membership_role' => 'member',
            'added_at' => now(),
            'is_active_member' => true,
        ], $overrides));
    }
}
