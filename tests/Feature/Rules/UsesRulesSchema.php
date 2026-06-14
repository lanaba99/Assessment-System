<?php

declare(strict_types=1);

namespace Tests\Feature\Rules;

use App\Domains\Grading\Models\Grade;
use App\Domains\Rules\Models\EligibilityChain;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Feature\ExamSession\UsesExamSessionSchema;

/**
 * Extends the ExamSession schema with eligibility-chain and grade tables
 * required by Rules-domain integration tests.
 */
trait UsesRulesSchema
{
    use UsesExamSessionSchema;

    protected function bootRulesSchema(): void
    {
        $this->bootExamSessionSchema();
        $this->migrateRulesTables();
    }

    private function migrateRulesTables(): void
    {
        $connection = (string) config('database.default');

        if ($connection !== 'sqlite') {
            Schema::connection($connection)->disableForeignKeyConstraints();
            Schema::connection($connection)->dropIfExists('eligibility_chains');
            Schema::connection($connection)->dropIfExists('grades');
            Schema::connection($connection)->enableForeignKeyConstraints();
        }

        if (! Schema::hasTable('grades')) {
            Schema::create('grades', function (Blueprint $table): void {
                $table->uuid('grade_id')->primary();
                $table->uuid('session_id');
                $table->uuid('candidate_user_id');
                $table->uuid('exam_id');
                $table->uuid('tenant_id');
                $table->decimal('raw_score', 10, 4)->default(0);
                $table->decimal('weighted_score', 10, 4)->nullable();
                $table->decimal('normalized_score', 6, 2)->nullable();
                $table->decimal('final_score', 6, 2)->nullable();
                $table->string('grade_letter')->nullable();
                $table->boolean('is_passing_grade')->default(false);
                $table->boolean('requires_second_marking')->default(false);
                $table->boolean('is_final_grade')->default(false);
                $table->json('grading_metadata')->nullable();
                $table->dateTime('graded_at')->nullable();
                $table->dateTime('finalized_at')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->foreign('session_id')
                    ->references('session_id')->on('exam_sessions')
                    ->onUpdate('cascade')->onDelete('cascade');
                $table->foreign('candidate_user_id')
                    ->references('id')->on('users')
                    ->onUpdate('cascade')->onDelete('restrict');
                $table->foreign('exam_id')
                    ->references('exam_id')->on('exams')
                    ->onUpdate('cascade')->onDelete('restrict');

                $table->index('tenant_id');
                $table->index(['tenant_id', 'candidate_user_id', 'exam_id']);
            });
        }

        if (! Schema::hasTable('eligibility_chains')) {
            Schema::create('eligibility_chains', function (Blueprint $table): void {
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
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createEligibilityChainStep(
        string $tenantId,
        string $examId,
        string $createdByUserId,
        int $stepNumber,
        string $prerequisiteExamId,
        ?float $minScore = null,
        array $overrides = [],
    ): EligibilityChain {
        return EligibilityChain::query()->forceCreate(array_merge([
            'chain_id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'exam_id' => $examId,
            'created_by_user_id' => $createdByUserId,
            'chain_step_number' => $stepNumber,
            'prerequisite_exam_id' => $prerequisiteExamId,
            'condition_type' => 'prerequisite_exam',
            'logical_operator' => 'AND',
            'min_score_required' => $minScore,
            'is_satisfied_override_available' => false,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createPassingGradeForExam(
        string $tenantId,
        string $candidateId,
        string $examId,
        string $sessionId,
        float $normalizedScore = 75.0,
        array $overrides = [],
    ): Grade {
        return Grade::query()->forceCreate(array_merge([
            'grade_id' => (string) Str::uuid(),
            'session_id' => $sessionId,
            'candidate_user_id' => $candidateId,
            'exam_id' => $examId,
            'tenant_id' => $tenantId,
            'raw_score' => 7.5,
            'normalized_score' => $normalizedScore,
            'final_score' => $normalizedScore,
            'grade_letter' => 'B',
            'is_passing_grade' => true,
            'is_final_grade' => true,
            'finalized_at' => now(),
            'graded_at' => now(),
            'created_at' => now(),
        ], $overrides));
    }
}
