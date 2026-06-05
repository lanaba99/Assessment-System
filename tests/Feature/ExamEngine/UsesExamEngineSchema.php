<?php

declare(strict_types=1);

namespace Tests\Feature\ExamEngine;

use App\Domains\ExamEngine\Enums\ExamStatus;
use App\Domains\ExamEngine\Models\Exam;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Feature\Identity\UsesIdentitySchema;

/**
 * Builds the ExamEngine tables on top of the identity schema for feature tests.
 *
 * Curated migration list — keep in lockstep with production. Any migration that
 * alters an ExamEngine table MUST be appended here or the test schema drifts.
 *
 * Migration dependency order (enforced by filename sort and list order here):
 *   exams → exam_sections → exam_blueprints
 *
 * Deliberately excluded migrations and why:
 * - 2026_05_19_000020_create_exam_session_items_table.php
 *   Requires exam_sessions (FK) and question_versions (FK), which are not part
 *   of this focused schema. Add a UsesExamSessionSchema that builds the full
 *   session stack when ExamSession-level tests are introduced.
 * - 2026_05_19_000025_add_heartbeat_to_exam_sessions.php
 *   Alters exam_sessions, which is not built here.
 * - 2026_05_22_000010_change_version_lock_to_integer_on_exam_sessions_and_items.php
 *   Alters exam_sessions and exam_session_items, neither of which is built here.
 */
trait UsesExamEngineSchema
{
    use UsesIdentitySchema;

    protected function bootExamEngineSchema(): void
    {
        $this->bootIdentitySchema();
        $this->migrateExamEngineTables();
    }

    private function migrateExamEngineTables(): void
    {
        $files = [
            '2026_05_16_000220_create_exams_table.php',
            // Sections before blueprints: blueprints has a section_id FK.
            '2026_05_16_000230_create_exam_sections_table.php',
            '2026_05_16_000240_create_exam_blueprints_table.php',
        ];

        $basePath = database_path('migrations/tenant/02_assessment_and_exams');
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
            $migration = require $basePath . '/' . $file;
            $migration->up();
        }
    }

    /**
     * Create an Exam record directly via forceCreate, bypassing the HTTP stack.
     * Use this for test setup only; business-logic assertions must go through
     * the API endpoints.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function createExam(string $tenantId, string $createdByUserId, array $overrides = []): Exam
    {
        return Exam::query()->forceCreate(array_merge([
            'exam_id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'created_by_user_id' => $createdByUserId,
            'exam_name' => 'Test Exam ' . Str::random(6),
            'exam_code' => 'EXAM-' . strtoupper(Str::random(8)),
            'exam_description' => 'A test exam.',
            'exam_type' => 'certification',
            'assessment_mode' => 'online',
            'total_questions' => 30,
            'total_duration_minutes' => 60,
            'pass_mark_percentage' => 70.0,
            'difficulty_tier_level' => 2,
            'is_adaptive_exam' => false,
            'is_randomized' => true,
            'allow_review_after_submit' => false,
            'allow_flagging_for_review' => true,
            'timer_visible_to_candidate' => true,
            'show_correct_answers_after' => false,
            'security_protocols' => null,
            'exam_metadata' => null,
            'exam_status' => ExamStatus::Draft,
            'is_published' => false,
            'published_at' => null,
            'archived_at' => null,
        ], $overrides));
    }
}
