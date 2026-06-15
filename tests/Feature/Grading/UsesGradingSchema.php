<?php

declare(strict_types=1);

namespace Tests\Feature\Grading;

use App\Domains\ExamSession\Events\ExamSessionCompleted;
use App\Domains\Grading\DTOs\AssessmentSummary;
use App\Domains\Grading\Models\AnswerEvaluation;
use App\Domains\Grading\Models\AssessmentResult;
use App\Domains\Grading\Models\Grade;
use DateTimeImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Feature\ExamSession\UsesExamSessionSchema;
use Tests\Feature\Workflows\UsesWorkflowsSchema;

/**
 * Builds the Grading table stack on top of the ExamSession schema.
 *
 * Inline table definitions mirror the production migrations in
 * database/migrations/tenant/02_assessment_and_exams and
 * database/migrations/tenant/05_rules_and_penalties. Stub tables (categories,
 * questions) carry only the columns needed to satisfy FK constraints for
 * grading-focused tests.
 *
 * Tables built (dependency order):
 *   categories (stub) → questions (stub) → competencies (stub) →
 *   question_competency_weights → answer_evaluations → grades →
 *   assessment_results → competency_scores → proctoring_events →
 *   penalty_rules → penalty_sanctions
 *
 * Tables inherited from UsesExamSessionSchema:
 *   users, exams, exam_sections, exam_blueprints, exam_sessions, …
 */
trait UsesGradingSchema
{
    use UsesExamSessionSchema;
    use UsesWorkflowsSchema;

    protected function bootGradingSchema(): void
    {
        $this->bootExamSessionSchema();
        $this->migrateGradingTables();
        $this->migrateWorkflowTables();
    }

    private function migrateGradingTables(): void
    {
        $connection = (string) config('database.default');

        if ($connection !== 'sqlite') {
            Schema::connection($connection)->disableForeignKeyConstraints();

            foreach ([
                'penalty_sanctions',
                'penalty_rules',
                'proctoring_events',
                'competency_scores',
                'assessment_results',
                'grades',
                'answer_evaluations',
                'question_competency_weights',
                'competencies',
                'questions',
                'categories',
            ] as $table) {
                Schema::connection($connection)->dropIfExists($table);
            }

            Schema::connection($connection)->enableForeignKeyConstraints();
        }

        // ── categories (stub) ─────────────────────────────────────────────────
        Schema::create('categories', function (Blueprint $table): void {
            $table->uuid('category_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('parent_category_id')->nullable();
            $table->string('category_name');
            $table->string('category_code')->unique();
            $table->unsignedInteger('display_order')->default(0);
            $table->unsignedInteger('hierarchy_level')->default(0);
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        if (! Schema::hasColumn('question_versions', 'question_id')) {
            Schema::table('question_versions', function (Blueprint $table): void {
                $table->uuid('question_id')->nullable();
            });
        }

        // ── questions (stub) ────────────────────────────────────────────────
        Schema::create('questions', function (Blueprint $table): void {
            $table->uuid('question_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('category_id');
            $table->uuid('created_by_user_id');
            $table->string('question_title');
            $table->string('question_type');
            $table->unsignedTinyInteger('difficulty_level')->default(1);
            $table->unsignedTinyInteger('cognitive_level')->default(1);
            $table->boolean('is_randomizable')->default(true);
            $table->boolean('requires_media_attachment')->default(false);
            $table->boolean('is_deprecated')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->unsignedBigInteger('total_usage_count')->default(0);
            $table->timestamps();

            $table->foreign('category_id')
                ->references('category_id')->on('categories')
                ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('created_by_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')->onDelete('restrict');
        });

        // ── competencies (stub) ─────────────────────────────────────────────
        Schema::create('competencies', function (Blueprint $table): void {
            $table->uuid('competency_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('parent_competency_id')->nullable();
            $table->string('competency_name');
            $table->string('competency_code')->unique();
            $table->string('competency_type')->default('knowledge');
            $table->unsignedInteger('display_order')->default(0);
            $table->unsignedInteger('hierarchy_level')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
        });

        // ── question_competency_weights ─────────────────────────────────────
        Schema::create('question_competency_weights', function (Blueprint $table): void {
            $table->uuid('weight_id')->primary();
            $table->uuid('question_id');
            $table->uuid('competency_id');
            $table->decimal('weight_percentage', 5, 2)->default(0);
            $table->string('skill_category')->nullable();
            $table->string('skill_gap_trigger')->nullable();
            $table->boolean('is_primary_competency')->default(false);
            $table->json('weighting_metadata')->nullable();
            $table->timestamps();

            $table->foreign('question_id')
                ->references('question_id')->on('questions')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('competency_id')
                ->references('competency_id')->on('competencies')
                ->onUpdate('cascade')->onDelete('cascade');

            $table->unique(['question_id', 'competency_id']);
            $table->index('is_primary_competency');
        });

        // ── answer_evaluations ──────────────────────────────────────────────
        Schema::create('answer_evaluations', function (Blueprint $table): void {
            $table->uuid('evaluation_id')->primary();
            $table->uuid('session_id');
            $table->uuid('question_id');
            $table->uuid('evaluator_user_id');
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

        // ── grades ──────────────────────────────────────────────────────────
        Schema::create('grades', function (Blueprint $table): void {
            $table->uuid('grade_id')->primary();
            $table->uuid('session_id');
            $table->uuid('candidate_user_id');
            $table->uuid('exam_id');
            $table->uuid('tenant_id');

            $table->decimal('raw_score', 8, 2)->nullable();
            $table->decimal('weighted_score', 8, 2)->nullable();
            $table->decimal('normalized_score', 8, 2)->nullable();
            $table->decimal('final_score', 8, 2)->nullable();

            $table->string('grade_letter')->nullable();

            $table->boolean('is_passing_grade')->default(false);
            $table->boolean('requires_second_marking')->default(false);
            $table->boolean('is_final_grade')->default(false);

            $table->json('grading_metadata')->nullable();

            $table->timestamp('graded_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('version_lock')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('session_id')
                ->references('session_id')->on('exam_sessions')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('candidate_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('exam_id')
                ->references('exam_id')->on('exams')
                ->onUpdate('cascade')->onDelete('restrict');

            $table->index('tenant_id');
            $table->index('is_passing_grade');
            $table->index('is_final_grade');
        });

        // ── assessment_results ──────────────────────────────────────────────
        Schema::create('assessment_results', function (Blueprint $table): void {
            $table->uuid('result_id')->primary();
            $table->uuid('candidate_user_id');
            $table->uuid('session_id');
            $table->uuid('exam_id');
            $table->uuid('tenant_id');

            $table->string('result_status')->default('pending');

            $table->json('skill_radar_data_json')->nullable();
            $table->json('benchmark_comparison_data')->nullable();

            $table->text('ai_recommendation_text')->nullable();
            $table->decimal('ai_recommendation_confidence', 5, 4)->nullable();

            $table->json('performance_insights')->nullable();
            $table->json('learning_path_recommendations')->nullable();

            $table->dateTime('result_calculated_at')->nullable();

            $table->string('publication_status')->default('unpublished');
            $table->dateTime('published_at')->nullable();

            $table->json('result_metadata')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->foreign('candidate_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('session_id')
                ->references('session_id')->on('exam_sessions')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('exam_id')
                ->references('exam_id')->on('exams')
                ->onUpdate('cascade')->onDelete('restrict');

            $table->index('tenant_id');
            $table->index('result_status');
            $table->index('publication_status');
        });

        // ── competency_scores ───────────────────────────────────────────────
        Schema::create('competency_scores', function (Blueprint $table): void {
            $table->uuid('score_id')->primary();
            $table->uuid('candidate_user_id');
            $table->uuid('session_id');
            $table->uuid('competency_id');
            $table->uuid('tenant_id');

            $table->decimal('score_achieved', 8, 2)->nullable();
            $table->decimal('score_target', 8, 2)->nullable();
            $table->decimal('score_maximum', 8, 2)->nullable();
            $table->unsignedTinyInteger('proficiency_level_achieved')->nullable();
            $table->decimal('gap_percentage', 5, 2)->nullable();
            $table->string('gap_status')->nullable();
            $table->json('score_metadata')->nullable();
            $table->timestamp('calculated_at')->nullable();

            $table->foreign('candidate_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('session_id')
                ->references('session_id')->on('exam_sessions')
                ->onUpdate('cascade')->onDelete('cascade');

            $table->index('tenant_id');
            $table->index('competency_id');
            $table->index('gap_status');
        });

        // ── proctoring_events ───────────────────────────────────────────────
        Schema::create('proctoring_events', function (Blueprint $table): void {
            $table->uuid('event_id')->primary();
            $table->uuid('session_id');
            $table->uuid('candidate_user_id');
            $table->uuid('tenant_id');
            $table->uuid('reviewing_proctor_id')->nullable();
            $table->dateTime('event_timestamp');
            $table->string('event_type');
            $table->string('event_category')->nullable();
            $table->json('event_payload')->nullable();
            $table->json('detection_parameters')->nullable();
            $table->string('severity_level')->default('info');
            $table->decimal('detection_confidence_score', 5, 4)->nullable();
            $table->string('screenshot_url')->nullable();
            $table->string('video_segment_url')->nullable();
            $table->boolean('requires_investigation')->default(false);
            $table->boolean('is_escalated')->default(false);
            $table->string('investigation_status')->default('open');
            $table->json('investigation_notes')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('session_id')
                ->references('session_id')->on('exam_sessions')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('candidate_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('reviewing_proctor_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')->onDelete('set null');

            $table->index('tenant_id');
            $table->index('event_type');
            $table->index('event_category');
            $table->index('severity_level');
            $table->index('investigation_status');
            $table->index('requires_investigation');
            $table->index('is_escalated');
        });

        // ── penalty_rules ───────────────────────────────────────────────────
        Schema::create('penalty_rules', function (Blueprint $table): void {
            $table->uuid('penalty_rule_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('created_by_user_id');

            $table->string('penalty_name');
            $table->string('penalty_type');
            $table->string('trigger_condition');
            $table->json('trigger_parameters')->nullable();

            $table->decimal('penalty_points', 10, 4)->nullable();
            $table->decimal('penalty_percentage', 5, 2)->nullable();

            $table->boolean('is_cumulative')->default(false);
            $table->json('penalty_metadata')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->foreign('created_by_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')->onDelete('restrict');
        });

        // ── penalty_sanctions ───────────────────────────────────────────────
        Schema::create('penalty_sanctions', function (Blueprint $table): void {
            $table->uuid('sanction_id')->primary();
            $table->uuid('session_id');
            $table->uuid('candidate_user_id');
            $table->uuid('penalty_rule_id');
            $table->uuid('tenant_id');

            $table->dateTime('sanction_applied_at');
            $table->string('sanction_reason');
            $table->decimal('sanction_amount', 12, 4)->nullable();
            $table->string('sanction_type');
            $table->json('sanction_metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('session_id')
                ->references('session_id')->on('exam_sessions')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('candidate_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('penalty_rule_id')
                ->references('penalty_rule_id')->on('penalty_rules')
                ->onUpdate('cascade')->onDelete('restrict');
        });
    }

    // =========================================================================
    // Setup helpers — for test data creation only
    // =========================================================================

    /**
     * @return array{candidate: \App\Domains\Identity\Models\User, exam: \App\Domains\ExamEngine\Models\Exam, session: \App\Domains\ExamSession\Models\CandidateExamStatus}
     */
    protected function prepareGradingSession(): array
    {
        $candidate = $this->createUser($this->tenantA);
        [$exam] = $this->prepareExamWithMockedItems($this->tenantA, (string) $candidate->id);
        $enrollment = $this->createEnrollment(
            $this->tenantA,
            (string) $exam->exam_id,
            (string) $candidate->id,
        );
        $session = $this->createExamSession(
            $this->tenantA,
            (string) $exam->exam_id,
            (string) $enrollment->enrollment_id,
            (string) $candidate->id,
            ['session_state' => 'completed', 'session_ended_at' => now()],
        );

        return [
            'candidate' => $candidate,
            'exam' => $exam,
            'session' => $session,
        ];
    }

    protected function createQuestionStub(string $tenantId, string $createdByUserId): string
    {
        $categoryId = (string) Str::uuid();
        $questionId = (string) Str::uuid();

        DB::table('categories')->insert([
            'category_id' => $categoryId,
            'tenant_id' => $tenantId,
            'category_name' => 'Grading Test Category',
            'category_code' => 'GRD-' . strtoupper(Str::random(8)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('questions')->insert([
            'question_id' => $questionId,
            'tenant_id' => $tenantId,
            'category_id' => $categoryId,
            'created_by_user_id' => $createdByUserId,
            'question_title' => 'Grading Test Question',
            'question_type' => 'multiple_choice',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $questionId;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createAnswerEvaluation(
        string $tenantId,
        string $sessionId,
        string $questionId,
        string $evaluatorUserId,
        array $overrides = [],
    ): AnswerEvaluation {
        return AnswerEvaluation::query()->forceCreate(array_merge([
            'evaluation_id' => (string) Str::uuid(),
            'session_id' => $sessionId,
            'question_id' => $questionId,
            'evaluator_user_id' => $evaluatorUserId,
            'tenant_id' => $tenantId,
            'evaluation_type' => 'auto',
            'score_awarded' => 8.0,
            'max_score_possible' => 10.0,
            'evaluation_status' => 'scored',
            'evaluation_metadata' => ['is_correct' => true],
            'requires_secondary_review' => false,
            'evaluated_at' => now(),
            'created_at' => now(),
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createGrade(
        string $tenantId,
        string $sessionId,
        string $candidateUserId,
        string $examId,
        array $overrides = [],
    ): Grade {
        return Grade::query()->forceCreate(array_merge([
            'grade_id' => (string) Str::uuid(),
            'session_id' => $sessionId,
            'candidate_user_id' => $candidateUserId,
            'exam_id' => $examId,
            'tenant_id' => $tenantId,
            'raw_score' => 8.0,
            'weighted_score' => 8.0,
            'normalized_score' => 80.0,
            'final_score' => 80.0,
            'grade_letter' => 'B',
            'is_passing_grade' => true,
            'requires_second_marking' => false,
            'is_final_grade' => false,
            'grading_metadata' => [
                'total_evaluations' => 1,
                'pending_evaluations' => 0,
                'correct_count' => 1,
                'incorrect_count' => 0,
                'max_score' => 10.0,
                'breakdown' => [],
                'blueprint_weighted' => false,
                'penalty_deduction' => 0.0,
                'sanctions_applied' => [],
            ],
            'graded_at' => now(),
            'finalized_at' => null,
            'created_at' => now(),
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createAssessmentResult(
        string $tenantId,
        string $sessionId,
        string $candidateUserId,
        string $examId,
        array $overrides = [],
    ): AssessmentResult {
        return AssessmentResult::query()->forceCreate(array_merge([
            'result_id' => (string) Str::uuid(),
            'candidate_user_id' => $candidateUserId,
            'session_id' => $sessionId,
            'exam_id' => $examId,
            'tenant_id' => $tenantId,
            'result_status' => AssessmentSummary::STATUS_FINAL,
            'result_calculated_at' => now(),
            'publication_status' => 'unpublished',
            'published_at' => null,
            'result_metadata' => [
                'raw_score' => 8.0,
                'max_score' => 10.0,
                'percentage' => 80.0,
                'grade_letter' => 'B',
                'is_passing' => true,
                'total_evaluations' => 1,
                'pending_evaluations' => 0,
                'correct_count' => 1,
                'incorrect_count' => 0,
            ],
            'created_at' => now(),
        ], $overrides));
    }

    protected function buildAssessmentSummary(
        string $sessionId,
        string $candidateId,
        string $examId,
        bool $isFinal = true,
        float $percentage = 80.0,
    ): AssessmentSummary {
        return new AssessmentSummary(
            sessionId: $sessionId,
            candidateId: $candidateId,
            examId: $examId,
            tenantId: $this->tenantA,
            rawScore: 8.0,
            maxScore: 10.0,
            percentage: $percentage,
            gradeLetter: 'B',
            isPassing: $percentage >= 60.0,
            isFinal: $isFinal,
            totalEvaluations: 1,
            pendingEvaluations: $isFinal ? 0 : 1,
            correctCount: 1,
            incorrectCount: 0,
        );
    }

    protected function buildExamSessionCompletedEvent(
        string $sessionId,
        string $candidateId,
        string $examId,
    ): ExamSessionCompleted {
        return new ExamSessionCompleted(
            sessionId: $sessionId,
            tenantId: $this->tenantA,
            candidateId: $candidateId,
            examId: $examId,
            finalState: 'completed',
            completionMethod: 'manual_submit',
            endedAt: new DateTimeImmutable(),
            totalQuestionsResponded: 1,
            totalQuestionsFlagged: 0,
            versionLockAfter: 1,
        );
    }
}
