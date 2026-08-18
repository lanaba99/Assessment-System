<?php

declare(strict_types=1);

namespace Tests\Feature\ExamSession;

use App\Domains\ExamEngine\Models\ExamBlueprint;
use App\Domains\ExamEngine\Models\ExamSection;
use App\Domains\ExamSession\Models\CandidateExamStatus;
use App\Domains\ExamSession\Models\ExamCandidateEligible;
use Illuminate\Database\Schema\Blueprint as SchemaBlueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Feature\ExamEngine\UsesExamEngineSchema;

/**
 * Full schema stack needed to exercise LIVE Adaptive CAT end-to-end.
 *
 * UsesExamSessionSchema (the trait ordinary session-lifecycle tests use)
 * deliberately builds a STUB question_versions table — version_id and
 * tenant_id only. That is not enough for CAT: item selection reads
 * question_type, correct_answer_json, and the question's psychometrics +
 * competency links, none of which the stub has. This trait builds the
 * REAL production QuestionBank tables instead, plus competencies, on top
 * of the same exams/sections/blueprints/sessions stack.
 *
 * Tables built (dependency order):
 *   identity (users/roles/permissions) → exams → exam_sections →
 *   exam_blueprints → categories → questions → question_versions →
 *   question_psychometrics → competencies → question_competency_weights →
 *   exam_enrollments → exam_sessions → exam_session_items
 */
trait UsesAdaptiveCatSchema
{
    use UsesExamEngineSchema;

    protected function bootAdaptiveCatSchema(): void
    {
        $this->bootExamEngineSchema();
        $this->migrateQuestionBankTablesForCat();
        $this->migrateCompetencyTablesForCat();
        $this->migrateExamSessionTablesForCat();
    }

    // =========================================================================
    // Schema setup
    // =========================================================================

    private function migrateQuestionBankTablesForCat(): void
    {
        $files = [
            '2026_05_16_000140_create_categories_table.php',
            '2026_05_16_000150_create_questions_table.php',
            '2026_05_16_000160_create_question_versions_table.php',
            '2026_05_16_000180_create_question_options_table.php',
            '2026_05_19_000010_create_question_psychometrics_table.php',
            // Alter — adds deleted_at to questions/question_versions/categories.
            // Guarded with hasTable()/hasColumn(), safe to always include.
            '2026_06_05_000010_add_soft_deletes_to_question_bank_tables.php',
        ];

        $this->runProductionMigrations('tenant/02_assessment_and_exams', $files);
    }

    private function migrateCompetencyTablesForCat(): void
    {
        $files = [
            '2026_05_16_000360_create_competencies_table.php',
            '2026_06_05_000050_add_tree_to_competencies.php',
            '2026_05_16_000380_create_question_competency_weights_table.php',
        ];

        // competencies lives under a different subdirectory than the
        // QuestionBank files above.
        $this->runProductionMigrations('tenant/03_ksa_and_competencies', $files);
    }

    /**
     * @param  array<int, string>  $files
     */
    private function runProductionMigrations(string $subPath, array $files): void
    {
        $basePath = database_path('migrations/' . $subPath);
        $connection = (string) config('database.default');

        if ($connection !== 'sqlite') {
            Schema::connection($connection)->disableForeignKeyConstraints();

            foreach (array_reverse($files) as $file) {
                $migration = require $basePath . '/' . $file;

                try {
                    $migration->down();
                } catch (\Throwable) {
                    // Fresh databases have nothing to roll back.
                }
            }

            Schema::connection($connection)->enableForeignKeyConstraints();
        }

        foreach ($files as $file) {
            (require $basePath . '/' . $file)->up();
        }
    }

    private function migrateExamSessionTablesForCat(): void
    {
        $connection = (string) config('database.default');

        if ($connection !== 'sqlite') {
            Schema::connection($connection)->disableForeignKeyConstraints();

            foreach ([
                'question_responses', 'exam_session_items', 'exam_sessions',
                'exam_enrollments', 'eligibility_chains', 'answer_evaluations',
            ] as $table) {
                Schema::connection($connection)->dropIfExists($table);
            }

            Schema::connection($connection)->enableForeignKeyConstraints();
        }

        // ── exam_enrollments ──────────────────────────────────────────────────
        Schema::create('exam_enrollments', function (SchemaBlueprint $table): void {
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
        Schema::create('exam_sessions', function (SchemaBlueprint $table): void {
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

            $table->timestamp('last_heartbeat_at')->nullable();
            $table->json('heartbeat_metadata')->nullable();

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
        Schema::create('question_responses', function (SchemaBlueprint $table): void {
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
        Schema::create('exam_session_items', function (SchemaBlueprint $table): void {
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

        // ── answer_evaluations ────────────────────────────────────────────────
        // Defined inline (not the production migration) because that migration
        // FKs rubric_id → rubrics, a table this focused CAT schema doesn't
        // build. evaluator_user_id is nullable here directly (the "final
        // shape after alter migration" pattern used elsewhere in this file) —
        // grading writes null for auto-scored items, no human evaluator.
        Schema::create('answer_evaluations', function (SchemaBlueprint $table): void {
            $table->uuid('evaluation_id')->primary();
            $table->uuid('session_id');
            $table->uuid('question_id');
            $table->uuid('evaluator_user_id')->nullable();
            $table->uuid('tenant_id');
            $table->uuid('rubric_id')->nullable();

            $table->string('evaluation_type');
            $table->json('rubric_criteria_json')->nullable();

            $table->decimal('score_awarded', 8, 2)->nullable();
            $table->decimal('max_score_possible', 8, 2)->nullable();

            $table->string('evaluation_status')->default('pending');

            $table->json('evaluator_comments')->nullable();
            $table->json('evaluation_metadata')->nullable();

            $table->boolean('requires_secondary_review')->default(false);
            $table->uuid('secondary_reviewer_id')->nullable();

            $table->timestamp('evaluated_at')->nullable();
            $table->timestamp('secondary_reviewed_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('session_id')
                ->references('session_id')->on('exam_sessions')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('question_id')
                ->references('question_id')->on('questions')
                ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('evaluator_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('secondary_reviewer_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')->onDelete('set null');

            $table->index('tenant_id');
            $table->index('evaluation_status');
        });

        // ── eligibility_chains (stub) ─────────────────────────────────────────
        // Required by EligibilityEvaluatorService during startSession (Gate 6).
        Schema::create('eligibility_chains', function (SchemaBlueprint $table): void {
            $table->uuid('chain_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('exam_id');
            $table->uuid('created_by_user_id');
            $table->unsignedInteger('chain_step_number');
            $table->uuid('prerequisite_exam_id')->nullable();
            $table->string('condition_type');
            $table->json('condition_data')->nullable();
            $table->string('logical_operator')->nullable();
            $table->decimal('min_score_required', 6, 2)->nullable();
            $table->boolean('is_satisfied_override_available')->default(false);
            $table->uuid('override_authorized_by_user_id')->nullable();
            $table->json('chain_metadata')->nullable();
            $table->timestamps();

            $table->foreign('exam_id')
                ->references('exam_id')->on('exams')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('prerequisite_exam_id')
                ->references('exam_id')->on('exams')
                ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('created_by_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')->onDelete('restrict');

            $table->unique(['exam_id', 'chain_step_number']);
        });
    }

    // =========================================================================
    // Fixture helpers
    // =========================================================================

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
            'questions_in_section' => 0,
            'time_limit_minutes' => null,
            'branching_logic' => null,
            'section_metadata' => null,
        ], $overrides));
    }

    protected function createCategoryForCat(string $tenantId, array $overrides = []): string
    {
        $categoryId = (string) Str::uuid();

        DB::table('categories')->insert(array_merge([
            'category_id' => $categoryId,
            'tenant_id' => $tenantId,
            'parent_category_id' => null,
            'category_name' => 'CAT Category ' . Str::random(6),
            'category_code' => 'CATC-' . strtoupper(Str::random(8)),
            'display_order' => 0,
            'hierarchy_level' => 0,
            'is_locked' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return $categoryId;
    }

    protected function createCompetencyForCat(string $tenantId, string $userId, array $overrides = []): string
    {
        $competencyId = (string) Str::uuid();

        DB::table('competencies')->insert(array_merge([
            'competency_id' => $competencyId,
            'tenant_id' => $tenantId,
            'created_by_user_id' => $userId,
            'competency_name' => 'Competency ' . Str::random(8),
            'competency_type' => 'technical',
            'is_mandatory' => false,
            'is_active' => true,
            'proficiency_level_count' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return $competencyId;
    }

    /**
     * Creates a fully calibrated, auto-gradable question version wired to
     * one competency at 100% weight — the minimum an adaptive CAT section
     * needs in its eligible pool. Returns the question_version_id.
     */
    protected function createCalibratedVersion(
        string $tenantId,
        string $categoryId,
        string $userId,
        string $competencyId,
        string $questionType = 'mcq',
        float $difficultyIndex = 0.5,
        bool $isCalibrated = true,
        ?string $correctAnswerJson = null,
    ): string {
        $questionId = (string) Str::uuid();
        $versionId = (string) Str::uuid();

        DB::table('questions')->insert([
            'question_id' => $questionId,
            'tenant_id' => $tenantId,
            'category_id' => $categoryId,
            'created_by_user_id' => $userId,
            'current_version_id' => null,
            'question_title' => 'CAT Question ' . Str::random(6),
            'question_type' => $questionType,
            'difficulty_level' => 1,
            'cognitive_level' => 1,
            'is_randomizable' => true,
            'requires_media_attachment' => false,
            'is_deprecated' => false,
            'is_archived' => false,
            'total_usage_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('question_versions')->insert([
            'version_id' => $versionId,
            'question_id' => $questionId,
            'created_by_user_id' => $userId,
            'ver_num' => 1,
            'question_text' => 'What is the answer?',
            'question_type' => $questionType,
            'options_json' => json_encode(['A', 'B', 'C', 'D']),
            'correct_answer_json' => $correctAnswerJson ?? json_encode(['B']),
            'approval_status' => 'approved',
            'approved_at' => now(),
            'created_at' => now(),
        ]);

        DB::table('question_psychometrics')->insert([
            'psychometric_id' => (string) Str::uuid(),
            'question_version_id' => $versionId,
            'tenant_id' => $tenantId,
            'difficulty_index' => $difficultyIndex,
            'discrimination_index' => 0.35,
            'sample_size' => 50,
            'correct_count' => 25,
            'is_calibrated' => $isCalibrated,
            'calibration_status' => $isCalibrated ? 'calibrated' : 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('question_competency_weights')->insert([
            'weight_id' => (string) Str::uuid(),
            'question_id' => $questionId,
            'competency_id' => $competencyId,
            'weight_percentage' => 100,
            'is_primary_competency' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $versionId;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createAdaptiveBlueprint(
        string $examId,
        ?string $sectionId,
        string $competencyId,
        array $overrides = [],
    ): ExamBlueprint {
        return ExamBlueprint::query()->forceCreate(array_merge([
            'blueprint_id' => (string) Str::uuid(),
            'exam_id' => $examId,
            'section_id' => $sectionId,
            'competency_id' => $competencyId,
            'min_questions_count' => 2,
            'max_questions_count' => 2,
            'min_weight_percentage' => 100,
            'max_weight_percentage' => 100,
            'bloom_distribution' => null,
            'target_difficulty' => 0.600,
            'min_discrimination' => 0.200,
            'resolution_strategy' => 'adaptive',
            'blueprint_metadata' => null,
            'created_at' => now(),
        ], $overrides));
    }

    /**
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
     * @param  array<string, mixed>  $overrides
     */
    protected function createExamSessionForCat(
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

    // =========================================================================
    // Shared HTTP-flow helpers for AdaptiveCatTest
    // =========================================================================
    //
    // These live here (as trait methods, not global Pest functions) because
    // several of them call protected setup methods above (createUser,
    // createExam, ...). A plain top-level `function` in a Pest test file
    // does NOT run inside the test class's scope, so it cannot call a
    // protected method even when handed the test instance explicitly —
    // PHP's visibility rules are scope-based, not reference-based. As a
    // trait method mixed into the same dynamically-generated test class,
    // these have the same scope as the test itself.

    /**
     * Two-section adaptive exam:
     *   Section 1 → competency A, max 2 items, pool of 3 calibrated mcq items.
     *   Section 2 → competency B, max 1 item,  pool of 1 calibrated mcq item.
     * Every version's correct answer is ['B'].
     */
    protected function setUpTwoSectionAdaptiveExam(): array
    {
        $admin = $this->createUser($this->tenantA, overrides: ['user_type' => 'admin']);
        $candidate = $this->createUser($this->tenantA);

        $exam = $this->createExam($this->tenantA, (string) $admin->id, [
            'exam_status' => \App\Domains\ExamEngine\Enums\ExamStatus::Published,
            'is_published' => true,
            'is_adaptive_exam' => true,
        ]);

        $section1 = $this->createExamSection((string) $exam->exam_id, $this->tenantA, ['section_sequence' => 1]);
        $section2 = $this->createExamSection((string) $exam->exam_id, $this->tenantA, ['section_sequence' => 2]);

        $category = $this->createCategoryForCat($this->tenantA);
        $competencyA = $this->createCompetencyForCat($this->tenantA, (string) $admin->id);
        $competencyB = $this->createCompetencyForCat($this->tenantA, (string) $admin->id);

        $this->createAdaptiveBlueprint((string) $exam->exam_id, (string) $section1->section_id, $competencyA, [
            'max_questions_count' => 2,
        ]);
        $this->createAdaptiveBlueprint((string) $exam->exam_id, (string) $section2->section_id, $competencyB, [
            'max_questions_count' => 1,
        ]);

        $section1Versions = [];
        for ($i = 0; $i < 3; $i++) {
            $section1Versions[] = $this->createCalibratedVersion(
                $this->tenantA, $category, (string) $admin->id, $competencyA,
            );
        }

        $section2Versions = [
            $this->createCalibratedVersion($this->tenantA, $category, (string) $admin->id, $competencyB),
        ];

        $enrollment = $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);

        return compact('admin', 'candidate', 'exam', 'section1', 'section2', 'competencyA', 'competencyB', 'section1Versions', 'section2Versions', 'enrollment');
    }

    protected function startAdaptive(array $ctx): \Illuminate\Testing\TestResponse
    {
        $this->grantPermissionsToUser($ctx['candidate'], ['exam_sessions.start']);
        \Laravel\Sanctum\Sanctum::actingAs($ctx['candidate']);

        return $this->postJson('/api/v1/exam-sessions/', ['exam_id' => (string) $ctx['exam']->exam_id])
            ->assertCreated();
    }

    protected function submitCorrect(string $sessionId, string $sessionItemId): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/exam-sessions/' . $sessionId . '/responses', [
            'session_item_id' => $sessionItemId,
            'response_type' => 'mcq',
            'selected_options' => ['B'],
        ]);
    }
}